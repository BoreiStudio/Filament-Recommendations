<?php

namespace BoreiStudio\FilamentRecommendations\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Provides normalized content-based features for a model that does not
 * implement Recommendable (e.g. a Product model owned by another plugin).
 *
 * Host projects and other Borei Studio plugins bind their own provider so
 * content-based strategies work without modifying the model itself.
 */
interface FeatureProvider
{
    /**
     * @return array<string, mixed>
     */
    public function features(Model $model): array;

    /**
     * Whether this provider handles the given model class.
     */
    public function supports(Model $model): bool;
}
