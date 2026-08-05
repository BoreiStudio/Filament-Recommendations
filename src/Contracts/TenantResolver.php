<?php

namespace BoreiStudio\FilamentRecommendations\Contracts;

interface TenantResolver
{
    /**
     * Resolve the id of the current tenant, or null when running in
     * single-tenant mode (or when no tenant context is available).
     */
    public function resolveTenantId(): int|string|null;
}
