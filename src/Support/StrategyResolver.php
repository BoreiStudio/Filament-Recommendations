<?php

namespace BoreiStudio\FilamentRecommendations\Support;

use BoreiStudio\FilamentRecommendations\Contracts\Recommendable;
use BoreiStudio\FilamentRecommendations\Models\RecommendationSetting;
use BoreiStudio\FilamentRecommendations\Strategies\RecommendationStrategy;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class StrategyResolver
{
    public function resolve(string|RecommendationStrategy|null $strategy = null, ?Model $subject = null): RecommendationStrategy
    {
        $strategy = $strategy ?? $this->strategyFor($subject);

        if (is_object($strategy)) {
            return $strategy;
        }

        if (! is_subclass_of($strategy, RecommendationStrategy::class)) {
            throw new InvalidArgumentException("Unknown recommendation strategy [{$strategy}].");
        }

        return app($strategy);
    }

    protected function strategyFor(?Model $subject): string
    {
        if ($subject !== null) {
            $tenantId = $subject instanceof Recommendable ? $subject->getRecommendationTenantId() : null;

            if (filled($stored = RecommendationSetting::strategyFor($subject::class, $tenantId))) {
                return $stored;
            }

            $perModel = config('recommendations.strategies.models.'.$subject::class);

            if (is_string($perModel) && $perModel !== '') {
                return $perModel;
            }
        }

        return config('recommendations.strategies.default');
    }
}
