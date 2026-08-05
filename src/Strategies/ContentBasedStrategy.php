<?php

namespace BoreiStudio\FilamentRecommendations\Strategies;

use BoreiStudio\FilamentRecommendations\Contracts\Recommendable;
use BoreiStudio\FilamentRecommendations\Support\FeatureResolver;
use BoreiStudio\FilamentRecommendations\Support\RecommendationService;
use Illuminate\Database\Eloquent\Model;

/**
 * Content-based strategy.
 *
 * Compares the subject's normalized features against the other models of the
 * same class using a weighted combination of Jaccard similarity (categorical
 * / multi-value attributes) and normalized numeric similarity (min-max scaled
 * across the candidate set). Features come from getRecommendationFeatures()
 * when the model is Recommendable, or from the registered FeatureProvider.
 */
class ContentBasedStrategy implements RecommendationStrategy
{
    public function compute(Model $subject, int $limit = 10, int|string|null $tenantId = null): array
    {
        $resolver = app(FeatureResolver::class);

        if (! $resolver->supports($subject)) {
            return [];
        }

        $tenantId = $tenantId ?? app(RecommendationService::class)->resolveTenantId($subject);

        $subjectFeatures = $resolver->featuresFor($subject);

        $candidates = $resolver->featuresForMany(
            $subject::query()
                ->whereKeyNot($subject->getKey())
                ->get(),
        );

        $weights = config('recommendations.strategies.weights.'.$subject::class, []);

        /** @var array<int, array{model: Model, features: array<string, mixed>}> $candidates */
        $ranges = $this->numericRanges($subjectFeatures, $candidates);

        $results = [];

        foreach ($candidates as $candidate) {
            if (! $this->isSameTenant($candidate['model'], $tenantId)) {
                continue;
            }

            $score = $this->similarity($subjectFeatures, $candidate['features'], $weights, $ranges);

            $results[] = [
                'recommended_type' => $candidate['model']->getMorphClass(),
                'recommended_id' => $candidate['model']->getKey(),
                'score' => $score,
            ];
        }

        usort($results, fn ($a, $b) => $b['score'] <=> $a['score']);

        $results = array_slice($results, 0, $limit);

        $max = $results[0]['score'] ?? 0;

        if ($max > 0) {
            foreach ($results as &$result) {
                $result['score'] = round($result['score'] / $max, 6);
            }
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $featuresA
     * @param  array<string, mixed>  $featuresB
     * @param  array<string, int|float>  $weights
     * @param  array<string, array{min: float, max: float}>  $ranges
     */
    protected function similarity(array $featuresA, array $featuresB, array $weights, array $ranges): float
    {
        $keys = array_values(array_unique(array_merge(array_keys($featuresA), array_keys($featuresB))));

        $weightedSum = 0.0;
        $totalWeight = 0.0;

        foreach ($keys as $key) {
            $valueA = $featuresA[$key] ?? null;
            $valueB = $featuresB[$key] ?? null;

            if ($valueA === null && $valueB === null) {
                continue;
            }

            $weight = $weights[$key] ?? 1;

            $weightedSum += $weight * $this->featureSimilarity($key, $valueA, $valueB, $ranges);
            $totalWeight += $weight;
        }

        return $totalWeight > 0 ? $weightedSum / $totalWeight : 0.0;
    }

    /**
     * @param  array<string, array{min: float, max: float}>  $ranges
     */
    protected function featureSimilarity(string $key, mixed $valueA, mixed $valueB, array $ranges): float
    {
        if ($valueA === null || $valueB === null) {
            return 0.0;
        }

        if (is_numeric($valueA) && is_numeric($valueB)) {
            return $this->numericSimilarity((float) $valueA, (float) $valueB, $ranges[$key] ?? null);
        }

        return $this->jaccard($this->tokens($valueA), $this->tokens($valueB));
    }

    /**
     * Compute min/max ranges per numeric attribute across the subject and all
     * candidates, so numeric similarity can be normalized to [0, 1].
     *
     * @param  array<string, mixed>  $subjectFeatures
     * @param  array<int, array{model: Model, features: array<string, mixed>}>  $candidates
     * @return array<string, array{min: float, max: float}>
     */
    protected function numericRanges(array $subjectFeatures, array $candidates): array
    {
        $ranges = [];

        foreach ([$subjectFeatures, ...array_column($candidates, 'features')] as $features) {
            foreach ($features as $key => $value) {
                if (! is_numeric($value)) {
                    continue;
                }

                $value = (float) $value;

                $ranges[$key]['min'] = min($ranges[$key]['min'] ?? $value, $value);
                $ranges[$key]['max'] = max($ranges[$key]['max'] ?? $value, $value);
            }
        }

        return $ranges;
    }

    /**
     * @param  array{min: float, max: float}|null  $range
     */
    protected function numericSimilarity(float $a, float $b, ?array $range): float
    {
        if ($range === null || $range['max'] <= $range['min']) {
            return $a === $b ? 1.0 : 0.0;
        }

        $span = $range['max'] - $range['min'];

        return max(0.0, 1.0 - abs($a - $b) / $span);
    }

    /**
     * @return array<int, string>
     */
    protected function tokens(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_map(fn ($item) => mb_strtolower((string) $item), $value));
        }

        return [mb_strtolower((string) $value)];
    }

    protected function isSameTenant(Model $candidate, int|string|null $tenantId): bool
    {
        if (! config('recommendations.tenancy.enabled')) {
            return true;
        }

        return app(RecommendationService::class)->resolveTenantId($candidate) === $tenantId;
    }

    /**
     * @param  array<int, string>  $tokensA
     * @param  array<int, string>  $tokensB
     */
    protected function jaccard(array $tokensA, array $tokensB): float
    {
        $tokensA = array_values(array_unique($tokensA));
        $tokensB = array_values(array_unique($tokensB));

        if ($tokensA === [] && $tokensB === []) {
            return 1.0;
        }

        if ($tokensA === [] || $tokensB === []) {
            return 0.0;
        }

        $intersection = count(array_intersect($tokensA, $tokensB));
        $union = count(array_unique(array_merge($tokensA, $tokensB)));

        return $intersection / $union;
    }
}
