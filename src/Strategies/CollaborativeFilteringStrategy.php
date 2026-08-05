<?php

namespace BoreiStudio\FilamentRecommendations\Strategies;

use BoreiStudio\FilamentRecommendations\Support\RecommendationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Item-based collaborative filtering strategy.
 *
 * "Users who interacted with this item also interacted with these others."
 * Builds a user-item matrix from recommendation_events (actor -> subject) and
 * ranks candidate items by cosine similarity over the shared user space.
 *
 * NOTE: this strategy is more expensive than co-occurrence / content-based and
 * only becomes useful once there is a meaningful volume of per-user
 * interactions. It is meant for advanced / optional setups.
 */
class CollaborativeFilteringStrategy implements RecommendationStrategy
{
    public function compute(Model $subject, int $limit = 10, int|string|null $tenantId = null): array
    {
        $type = $subject->getMorphClass();
        $id = $subject->getKey();

        $tenantId = $tenantId ?? app(RecommendationService::class)->resolveTenantId($subject);

        if (config('recommendations.tenancy.enabled') && $tenantId === null) {
            return [];
        }

        $subjectUsers = $this->countSubjectUsers($type, $id, $tenantId);

        if ($subjectUsers === 0) {
            return [];
        }

        $rows = DB::table('recommendation_events as e1')
            ->join('recommendation_events as e2', function ($join) {
                $join->on('e2.actor_type', '=', 'e1.actor_type')
                    ->on('e2.actor_id', '=', 'e1.actor_id');
            })
            ->selectRaw('e2.subject_type as recommended_type, e2.subject_id as recommended_id')
            ->selectRaw('COUNT(DISTINCT e2.actor_id) as intersect_count')
            ->selectRaw(
                '(SELECT COUNT(DISTINCT e3.actor_id) FROM recommendation_events e3 '
                .'WHERE e3.subject_type = e2.subject_type AND e3.subject_id = e2.subject_id '
                .($tenantId !== null ? 'AND e3.tenant_id = '.DB::connection()->getPdo()->quote((string) $tenantId).' ' : '')
                .') as candidate_users'
            )
            ->where('e1.subject_type', $type)
            ->where('e1.subject_id', $id)
            ->where('e2.subject_type', $type)
            ->where('e2.subject_id', '<>', $id)
            ->whereNotNull('e1.actor_id')
            ->when($tenantId !== null, fn ($query) => $query->where('e1.tenant_id', $tenantId)->where('e2.tenant_id', $tenantId))
            ->groupBy('e2.subject_type', 'e2.subject_id')
            ->orderByDesc('intersect_count')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $results = $rows->map(function ($row) use ($subjectUsers) {
            $candidateUsers = (int) $row->candidate_users;

            if ($candidateUsers === 0) {
                return;
            }

            $cosine = $row->intersect_count / (sqrt($subjectUsers) * sqrt($candidateUsers));

            return [
                'recommended_type' => $row->recommended_type,
                'recommended_id' => $row->recommended_id,
                'score' => round($cosine, 6),
            ];
        })->filter()->values();

        $max = $results->max('score') ?? 1;

        return $results->map(fn (array $result) => [
            'recommended_type' => $result['recommended_type'],
            'recommended_id' => $result['recommended_id'],
            'score' => round($result['score'] / $max, 6),
        ])->all();
    }

    protected function countSubjectUsers(string $type, int|string $id, int|string|null $tenantId): int
    {
        $query = DB::table('recommendation_events')
            ->select('actor_type', 'actor_id')
            ->where('subject_type', $type)
            ->where('subject_id', $id)
            ->whereNotNull('actor_id');

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        return (int) DB::table($query, 'actors')->count();
    }
}
