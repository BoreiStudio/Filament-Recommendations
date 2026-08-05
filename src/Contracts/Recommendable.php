<?php

namespace BoreiStudio\FilamentRecommendations\Contracts;

interface Recommendable
{
    /**
     * Return the normalized attributes used by content-based strategies.
     *
     * @return array<string, mixed>
     */
    public function getRecommendationFeatures(): array;

    /**
     * Stable unique key for this model within its tenant (usually the primary key).
     */
    public function getRecommendationKey(): string|int;

    /**
     * Tenant this model belongs to, used to scope recommendations. Defaults to
     * delegating to the current TenantResolver, but models may override it.
     */
    public function getRecommendationTenantId(): int|string|null;
}
