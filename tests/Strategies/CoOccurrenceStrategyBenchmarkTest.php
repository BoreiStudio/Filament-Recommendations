<?php

use BoreiStudio\FilamentRecommendations\Contracts\TenantResolver;
use BoreiStudio\FilamentRecommendations\Strategies\CoOccurrenceStrategy;
use BoreiStudio\FilamentRecommendations\Tests\Fixtures\FakeTenantResolver;
use BoreiStudio\FilamentRecommendations\Tests\Fixtures\Product;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->resolver = new FakeTenantResolver;
    app()->instance(TenantResolver::class, $this->resolver);
});

it('computes co-occurrence for ~10k events across several tenants quickly', function () {
    $tenants = 5;
    $users = 20;
    $products = 100;
    $eventsPerUser = 10;

    $this->resolver->setTenantId('bench');

    $productsByTenant = [];
    $inserts = [];

    foreach (range(1, $tenants) as $tenantIndex) {
        $tenantId = 'tenant-'.$tenantIndex;

        $this->resolver->setTenantId($tenantId);

        $models = collect(range(1, $products))->map(fn ($i) => Product::create([
            'name' => "T{$tenantIndex} P{$i}",
        ]));

        $productsByTenant[$tenantId] = $models;

        foreach (range(1, $users) as $user) {
            $seen = $models->random(min(3, $products));

            foreach (range(1, $eventsPerUser) as $eventIndex) {
                $subject = $models->random();
                $related = $seen->random();

                $inserts[] = [
                    'subject_type' => $subject->getMorphClass(),
                    'subject_id' => $subject->getKey(),
                    'related_type' => $related->getMorphClass(),
                    'related_id' => $related->getKey(),
                    'action' => 'viewed',
                    'tenant_id' => $tenantId,
                    'created_at' => now(),
                ];
            }
        }
    }

    foreach (array_chunk($inserts, 500) as $chunk) {
        DB::table('recommendation_events')->insert($chunk);
    }

    $strategy = new CoOccurrenceStrategy;

    $start = microtime(true);

    $results = collect($productsByTenant['tenant-1'])
        ->take(20)
        ->map(fn (Product $product) => $strategy->compute($product, 10));

    $duration = microtime(true) - $start;

    // 20 subjects x ~10k events must resolve in a reasonable time.
    expect($duration)->toBeLessThan(3.0);

    // Every result must be tenant-scoped: only tenant-1 products.
    foreach ($results as $result) {
        $ids = collect($result)->pluck('recommended_id');

        if ($ids->isEmpty()) {
            continue;
        }

        expect($ids->intersect($productsByTenant['tenant-1']->pluck('id')))->toHaveCount($ids->count());
    }
});
