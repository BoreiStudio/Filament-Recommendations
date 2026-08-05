<?php

namespace BoreiStudio\FilamentRecommendations\Strategies;

use BoreiStudio\FilamentRecommendations\Support\RecommendationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * "Whoever viewed/bought this also viewed/bought that" strategy.
 *
 * Counts how many times two items co-occur in the same interaction
 * (subject + related) according to the recommendation_events table. The
 * aggregation happens in SQL (GROUP BY + COUNT), never in PHP, and every
 * subquery is scoped by tenant_id when tenancy is enabled.
 */
class CoOccurrenceStrategy implements RecommendationStrategy
{
    public function compute(Model $subject, int $limit = 10, int|string|null $tenantId = null): array
    {
        $type = $subject->getMorphClass();
        $id = $subject->getKey();

        $tenantId = $tenantId ?? app(RecommendationService::class)->resolveTenantId($subject);

        if (config('recommendations.tenancy.enabled') && $tenantId === null) {
            return [];
        }

        $events = DB::table('recommendation_events');

        $asSubject = (clone $events)
            ->selectRaw('related_type as candidate_type, related_id as candidate_id')
            ->where('subject_type', $type)
            ->where('subject_id', $id)
            ->whereNotNull('related_type')
            ->whereNotNull('related_id');

        $asRelated = (clone $events)
            ->selectRaw('subject_type as candidate_type, subject_id as candidate_id')
            ->where('related_type', $type)
            ->where('related_id', $id)
            ->whereNotNull('subject_type')
            ->whereNotNull('subject_id');

        if (config('recommendations.tenancy.enabled')) {
            $asSubject->where('tenant_id', $tenantId);
            $asRelated->where('tenant_id', $tenantId);
        }

        $rows = DB::query()
            ->fromSub($asSubject->unionAll($asRelated), 'pairs')
            ->selectRaw('candidate_type, candidate_id, COUNT(*) as cnt')
            ->whereNot(fn ($query) => $query
                ->where('candidate_type', $type)
                ->where('candidate_id', $id))
            ->groupBy('candidate_type', 'candidate_id')
            ->orderByDesc('cnt')
            ->limit($limit)
            ->get();

        $max = $rows->max('cnt') ?? 1;

        return $rows->map(fn ($row) => [
            'recommended_type' => $row->candidate_type,
            'recommended_id' => $row->candidate_id,
            'score' => round((float) $row->cnt / (float) $max, 6),
        ])->all();
    }
}
