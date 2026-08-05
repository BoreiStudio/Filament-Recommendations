<?php

namespace BoreiStudio\FilamentRecommendations\Jobs;

use BoreiStudio\FilamentRecommendations\Models\Recommendation;
use BoreiStudio\FilamentRecommendations\Strategies\RecommendationStrategy;
use BoreiStudio\FilamentRecommendations\Support\RecommendationCache;
use BoreiStudio\FilamentRecommendations\Support\RecommendationService;
use BoreiStudio\FilamentRecommendations\Support\StrategyResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class RecalculateRecommendationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Model $subject,
        public string|RecommendationStrategy|null $strategy = null,
        public int|string|null $tenantId = null,
    ) {}

    public function handle(?StrategyResolver $resolver = null): void
    {
        $resolver ??= app(StrategyResolver::class);

        $strategy = $resolver->resolve($this->strategy, $this->subject);

        $tenantId = $this->tenantId ?? app(RecommendationService::class)->resolveTenantId($this->subject);

        $results = $strategy->compute($this->subject, 100, $tenantId);

        $strategyName = $strategy::class;

        DB::transaction(function () use ($strategyName, $tenantId, $results) {
            $this->clearExisting($strategyName, $tenantId);

            $rows = array_map(
                fn (array $result) => [
                    'source_type' => $this->subject->getMorphClass(),
                    'source_id' => $this->subject->getKey(),
                    'recommended_type' => $result['recommended_type'],
                    'recommended_id' => $result['recommended_id'],
                    'score' => $result['score'],
                    'strategy' => $strategyName,
                    'tenant_id' => $tenantId,
                    'computed_at' => now(),
                ],
                $results,
            );

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('recommendations')->insert($chunk);
            }
        });

        RecommendationCache::forget($this->subject, $tenantId);
    }

    protected function clearExisting(string $strategyName, int|string|null $tenantId): void
    {
        Recommendation::query()
            ->withoutGlobalScope('recommendation_tenant')
            ->where('source_type', $this->subject->getMorphClass())
            ->where('source_id', $this->subject->getKey())
            ->where('strategy', $strategyName)
            ->where('tenant_id', $tenantId)
            ->delete();
    }
}
