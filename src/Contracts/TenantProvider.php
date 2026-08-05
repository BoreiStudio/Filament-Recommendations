<?php

namespace BoreiStudio\FilamentRecommendations\Contracts;

interface TenantProvider
{
    /**
     * Return the ids of all active tenants, used by the rebuild command to
     * iterate every tenant when tenancy is enabled.
     *
     * @return array<int, int|string>
     */
    public function allTenantIds(): array;
}
