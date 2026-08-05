<?php

namespace BoreiStudio\FilamentRecommendations\Support;

use BoreiStudio\FilamentRecommendations\Contracts\FeatureProvider;
use BoreiStudio\FilamentRecommendations\Contracts\Recommendable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Resolves the content-based features for a model.
 *
 * Priority:
 *  1. The model's own getRecommendationFeatures() when it is Recommendable.
 *  2. FeatureProviders added via addProvider() (config-driven).
 *  3. The FeatureProvider bound in the container (plugin integrations).
 */
class FeatureResolver
{
    /**
     * @var array<int, FeatureProvider>
     */
    protected array $providers = [];

    public function addProvider(FeatureProvider $provider): void
    {
        $this->providers[] = $provider;
    }

    /**
     * @return array<string, mixed>
     */
    public function featuresFor(Model $model): array
    {
        if ($model instanceof Recommendable) {
            return $model->getRecommendationFeatures();
        }

        foreach ($this->allProviders() as $provider) {
            if ($provider->supports($model)) {
                return $provider->features($model);
            }
        }

        return [];
    }

    public function supports(Model $model): bool
    {
        if ($model instanceof Recommendable) {
            return true;
        }

        foreach ($this->allProviders() as $provider) {
            if ($provider->supports($model)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, Model>  $models
     * @return array<int, array{model: Model, features: array<string, mixed>}>
     */
    public function featuresForMany(Collection $models): array
    {
        return $models
            ->filter(fn (Model $model) => $this->supports($model))
            ->map(fn (Model $model) => [
                'model' => $model,
                'features' => $this->featuresFor($model),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, FeatureProvider>
     */
    protected function allProviders(): array
    {
        $providers = $this->providers;

        if (app()->bound(FeatureProvider::class)) {
            $providers[] = app(FeatureProvider::class);
        }

        return $providers;
    }
}
