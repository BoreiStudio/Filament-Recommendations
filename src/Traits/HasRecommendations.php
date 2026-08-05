<?php

namespace BoreiStudio\FilamentRecommendations\Traits;

use BoreiStudio\FilamentRecommendations\Contracts\TenantResolver;
use BoreiStudio\FilamentRecommendations\Jobs\RecalculateRecommendationsJob;
use BoreiStudio\FilamentRecommendations\Models\Recommendation;
use BoreiStudio\FilamentRecommendations\Models\RecommendationEvent;
use BoreiStudio\FilamentRecommendations\Models\RecommendationSetting;
use BoreiStudio\FilamentRecommendations\Relations\TenantAwareMorphMany;
use BoreiStudio\FilamentRecommendations\Support\RecommendationCache;
use BoreiStudio\FilamentRecommendations\Support\RecommendationsGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

trait HasRecommendations
{
    /**
     * Cached recommendations for this model (the "source" side).
     */
    public function recommendations(): TenantAwareMorphMany
    {
        $instance = $this->newRelatedInstance(Recommendation::class);

        [$type, $id] = $this->getMorphs('source', null, null);

        $table = $instance->getTable();

        return new TenantAwareMorphMany(
            $instance->newQuery(),
            $this,
            $table.'.'.$type,
            $table.'.'.$id,
            $this->getKeyName(),
            $this->getKeyName(),
        );
    }

    /**
     * Return the cached recommendations for this model, most relevant first.
     * Models recommended by more than one strategy are deduplicated.
     *
     * @return Collection<int, Model>
     */
    public function recommend(?int $limit = null): Collection
    {
        RecommendationsGuard::assertReady();

        $tenantId = $this->getRecommendationTenantId();

        $limit ??= RecommendationSetting::limitFor(static::class, $tenantId)
            ?? config('recommendations.results.limit', 10);

        $recommendations = RecommendationCache::remember(
            $this,
            $tenantId,
            fn () => $this->recommendationIds(),
            fn (array $ids) => $this->rehydrateRecommendations($ids),
        );

        return $recommendations->take($limit)->values();
    }

    /**
     * @return array<int, array{0: string, 1: int|string}>
     */
    protected function recommendationIds(): array
    {
        return $this->recommendations()
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

    /**
     * Log an interaction event (viewed, purchased, added to cart, ...). The
     * tenant id is resolved at the moment the event is recorded.
     *
     * @param  Model|null  $context  the related model (co-occurrence)
     * @param  Model|null  $actor  the user who performed the interaction (collaborative filtering)
     */
    public function recordInteraction(string $action, ?Model $context = null, ?Model $actor = null): RecommendationEvent
    {
        if (! config('recommendations.events.enabled', true)) {
            return new RecommendationEvent;
        }

        $event = new RecommendationEvent([
            'action' => $action,
            'tenant_id' => $this->getRecommendationTenantId(),
        ]);

        $event->subject()->associate($this);

        if ($context !== null) {
            $event->related()->associate($context);
        }

        if ($actor !== null) {
            $event->actor()->associate($actor);
        }

        $event->save();

        if (config('recommendations.events.recalculate_on_interaction')) {
            RecalculateRecommendationsJob::dispatch($this);

            if ($context !== null) {
                RecalculateRecommendationsJob::dispatch($context);
            }
        }

        return $event;
    }

    /**
     * Default implementation of the Recommendable contract. Models may
     * override this to provide their own normalized attributes.
     */
    public function getRecommendationFeatures(): array
    {
        return [];
    }

    /**
     * Default implementation of the Recommendable contract.
     */
    public function getRecommendationKey(): string|int
    {
        return $this->getKey();
    }

    /**
     * Default implementation of the Recommendable contract. Delegates to the
     * configured TenantResolver so it works in both single- and multi-tenant
     * modes. Models may override it to return a fixed tenant.
     */
    public function getRecommendationTenantId(): int|string|null
    {
        return app(TenantResolver::class)->resolveTenantId();
    }
}
