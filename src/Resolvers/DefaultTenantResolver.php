<?php

namespace BoreiStudio\FilamentRecommendations\Resolvers;

use BoreiStudio\FilamentRecommendations\Contracts\TenantResolver;
use BoreiStudio\FilamentRecommendations\Exceptions\TenantResolverNotConfigured;

/**
 * Default resolver used when no custom resolver is configured. In
 * single-tenant mode it returns null. If tenancy is enabled without a custom
 * resolver, it throws a clear configuration error.
 */
class DefaultTenantResolver implements TenantResolver
{
    public function resolveTenantId(): int|string|null
    {
        if (config('recommendations.tenancy.enabled')) {
            throw TenantResolverNotConfigured::create();
        }

        return null;
    }
}
