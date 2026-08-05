<?php

namespace BoreiStudio\FilamentRecommendations\Models\Concerns;

use BoreiStudio\FilamentRecommendations\Contracts\TenantResolver;
use Illuminate\Database\Eloquent\Builder;

trait ScopedByTenant
{
    protected static function bootScopedByTenant(): void
    {
        static::addGlobalScope('recommendation_tenant', function (Builder $builder) {
            if (! config('recommendations.tenancy.enabled')) {
                return;
            }

            $tenantId = static::resolveRecommendationTenantId();

            $column = $builder->qualifyColumn('tenant_id');

            if ($tenantId === null) {
                $builder->whereNull($column);

                return;
            }

            $builder->where($column, $tenantId);
        });
    }

    protected static function resolveRecommendationTenantId(): int|string|null
    {
        return app(TenantResolver::class)->resolveTenantId();
    }
}
