<?php

namespace BoreiStudio\FilamentRecommendations\Resolvers;

use BoreiStudio\FilamentRecommendations\Contracts\FeatureProvider;
use Illuminate\Database\Eloquent\Model;

/**
 * Fallback FeatureProvider used when no custom provider is bound. Returns no
 * features, which keeps content-based strategies safe for unknown models.
 */
class DefaultFeatureProvider implements FeatureProvider
{
    public function features(Model $model): array
    {
        return [];
    }

    public function supports(Model $model): bool
    {
        return false;
    }
}
