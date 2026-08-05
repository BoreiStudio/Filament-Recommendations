<?php

namespace BoreiStudio\FilamentRecommendations\Support;

use BoreiStudio\FilamentRecommendations\Contracts\Recommendable;
use BoreiStudio\FilamentRecommendations\Exceptions\MissingRecommendationsTable;
use BoreiStudio\FilamentRecommendations\Exceptions\NotARecommendableModel;
use BoreiStudio\FilamentRecommendations\Exceptions\TenantResolverNotConfigured;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class RecommendationsGuard
{
    /**
     * @phpstan-assert Recommendable $subject
     */
    public static function assertRecommendable(Model $subject): void
    {
        if (! $subject instanceof Recommendable) {
            throw NotARecommendableModel::make($subject::class);
        }
    }

    public static function assertTenancyConfigured(): void
    {
        if (! config('recommendations.tenancy.enabled')) {
            return;
        }

        $resolver = config('recommendations.tenancy.resolver');

        if (! is_string($resolver) || ! class_exists($resolver)) {
            throw TenantResolverNotConfigured::create();
        }
    }

    public static function assertTablesExist(): void
    {
        foreach (['recommendations', 'recommendation_events'] as $table) {
            if (! Schema::hasTable($table)) {
                throw MissingRecommendationsTable::make(
                    $table,
                    'Run php artisan migrate after publishing the package migrations.'
                );
            }
        }
    }

    /**
     * All runtime preconditions for computing or recording recommendations.
     *
     * Note: the tenancy configuration is validated lazily by
     * DefaultTenantResolver::resolveTenantId() when multi-tenant mode is
     * enabled without a custom resolver, so it does not need an upfront check
     * on every request.
     */
    public static function assertReady(): void
    {
        static::assertTablesExist();
    }
}
