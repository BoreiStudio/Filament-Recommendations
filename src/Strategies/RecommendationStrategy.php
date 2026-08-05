<?php

namespace BoreiStudio\FilamentRecommendations\Strategies;

use Illuminate\Database\Eloquent\Model;

interface RecommendationStrategy
{
    /**
     * Compute a ranked list of recommendations for the given subject.
     *
     * The subject is expected to be a Recommendable model (or at least expose
     * getRecommendationTenantId()), so the strategy can resolve the current
     * tenant context and scope the computation to it. When an explicit
     * $tenantId is provided (e.g. from a rebuild job), it takes precedence
     * over the one resolved from the subject.
     *
     * @return array<int, array{recommended_type: string, recommended_id: int|string, score: float}>
     */
    public function compute(Model $subject, int $limit = 10, int|string|null $tenantId = null): array;
}
