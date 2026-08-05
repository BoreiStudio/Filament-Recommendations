<?php

namespace BoreiStudio\FilamentRecommendations\Tests\Fixtures;

use BoreiStudio\FilamentRecommendations\Contracts\TenantResolver;

class FakeTenantResolver implements TenantResolver
{
    private int|string|null $tenantId = null;

    public function setTenantId(int|string|null $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function resolveTenantId(): int|string|null
    {
        return $this->tenantId;
    }
}
