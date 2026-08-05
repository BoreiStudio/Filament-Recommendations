<?php

namespace BoreiStudio\FilamentRecommendations\Support;

use BoreiStudio\FilamentRecommendations\Contracts\TenantResolver;
use BoreiStudio\FilamentRecommendations\Exceptions\TenantResolverNotConfigured;
use BoreiStudio\FilamentRecommendations\Jobs\RecalculateRecommendationsJob;
use BoreiStudio\FilamentRecommendations\Models\Recommendation;
use BoreiStudio\FilamentRecommendations\Models\RecommendationEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Service to record interactions and read recommendations for any Eloquent
 * model, without requiring it to use the HasRecommendations trait. Used by
 * host projects and by other Borei Studio plugins (e.g. filament-store).
 */
class RecommendationService
{
    /**
     * Log an interaction event (viewed, purchased, added to cart, ...).
     *
     * @param  Model|null  $context  the related model (co-occurrence)
     * @param  Model|null  $actor  the user who performed the interaction (collaborative filtering)
     */
    public function record(Model $subject, string $action, ?Model $context = null, ?Model $actor = null): RecommendationEvent
    {
        if (! config('recommendations.events.enabled', true)) {
            return new RecommendationEvent;
        }

        $tenantId = $this->resolveTenantId($subject);

        $event = new RecommendationEvent([
            'action' => $action,
            'tenant_id' => $tenantId,
        ]);

        $event->subject()->associate($subject);

        if ($context !== null) {
            $event->related()->associate($context);
        }

        if ($actor !== null) {
            $event->actor()->associate($actor);
        }

        $event->save();

        if (config('recommendations.events.recalculate_on_interaction')) {
            RecalculateRecommendationsJob::dispatch($subject);

            if ($context !== null) {
                RecalculateRecommendationsJob::dispatch($context);
            }
        }

        return $event;
    }

    /**
     * Read the cached recommendations for a subject model.
     *
     * @return Collection<int, Model>
     */
    public function for(Model $subject, ?int $limit = null): Collection
    {
        RecommendationsGuard::assertReady();

        $tenantId = $this->resolveTenantId($subject);

        $limit ??= config('recommendations.results.limit', 10);

        $recommendations = RecommendationCache::remember(
            $subject,
            $tenantId,
            fn () => $this->recommendationIds($subject),
            fn (array $ids) => $this->rehydrateRecommendations($ids),
        );

        return $recommendations->take($limit)->values();
    }

    public function resolveTenantId(Model $subject): int|string|null
    {
        if (method_exists($subject, 'getRecommendationTenantId')) {
            return $subject->getRecommendationTenantId();
        }

        if (app()->bound(TenantResolver::class)) {
            try {
                return app(TenantResolver::class)->resolveTenantId();
            } catch (TenantResolverNotConfigured) {
                return null;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{0: string, 1: int|string}>
     */
    protected function recommendationIds(Model $subject): array
    {
        return Recommendation::query()
            ->where('source_type', $subject->getMorphClass())
            ->where('source_id', $subject->getKey())
            ->orderByDesc('score')
            ->get()
            ->map(fn (Recommendation $recommendation) => [
                $recommendation->recommended_type,
                $recommendation->recommended_id,
            ])
            ->unique(fn (array $pair) => implode(':', $pair))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{0: string, 1: int|string}>  $ids
     * @return Collection<int, Model>
     */
    protected function rehydrateRecommendations(array $ids): Collection
    {
        $models = collect();

        foreach ($ids as [$type, $id]) {
            $model = (new $type)->newQuery()->find($id);

            if ($model !== null) {
                $models->push($model);
            }
        }

        return $models;
    }
}
