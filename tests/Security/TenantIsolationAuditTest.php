<?php

use BoreiStudio\FilamentRecommendations\Contracts\TenantResolver;
use BoreiStudio\FilamentRecommendations\Models\Recommendation;
use BoreiStudio\FilamentRecommendations\Models\RecommendationEvent;
use BoreiStudio\FilamentRecommendations\Models\RecommendationSetting;
use BoreiStudio\FilamentRecommendations\Strategies\CoOccurrenceStrategy;
use BoreiStudio\FilamentRecommendations\Tests\Fixtures\FakeTenantResolver;
use BoreiStudio\FilamentRecommendations\Tests\Fixtures\Product;

beforeEach(function () {
    $this->resolver = new FakeTenantResolver;
    app()->instance(TenantResolver::class, $this->resolver);

    config()->set('recommendations.events.recalculate_on_interaction', false);

    if (! $this->tenancyEnabled()) {
        $this->markTestSkipped('Tenant isolation only applies in multi-tenant mode.');
    }
});

function seedIsolationData(object $test): array
{
    $test->resolver->setTenantId('tenant-1');
    $a1 = Product::create(['name' => 'A1']);
    $a2 = Product::create(['name' => 'A2']);
    $a1->recordInteraction('viewed', $a2);
    $a2->recordInteraction('viewed', $a1);
    $a1->recommendations()->create([
        'recommended_type' => $a2->getMorphClass(),
        'recommended_id' => $a2->getKey(),
        'score' => 1.0,
        'strategy' => 'co-occurrence',
    ]);

    $test->resolver->setTenantId('tenant-2');
    $b1 = Product::create(['name' => 'B1']);
    $b2 = Product::create(['name' => 'B2']);
    $b1->recordInteraction('viewed', $b2);
    $b2->recordInteraction('viewed', $b1);
    $b1->recommendations()->create([
        'recommended_type' => $b2->getMorphClass(),
        'recommended_id' => $b2->getKey(),
        'score' => 1.0,
        'strategy' => 'co-occurrence',
    ]);

    $test->resolver->setTenantId('tenant-1');

    return compact('a1', 'a2', 'b1', 'b2');
}

it('scopes the Recommendation model queries to the current tenant', function () {
    $data = seedIsolationData($this);
    $a1 = $data['a1'];
    $b1 = $data['b1'];

    expect(Recommendation::count())->toBe(1);
    expect(Recommendation::pluck('source_id'))->toContain($a1->getKey());

    $this->resolver->setTenantId('tenant-2');
    expect(Recommendation::count())->toBe(1);
    expect(Recommendation::pluck('source_id'))->toContain($b1->getKey());
});

it('scopes the RecommendationEvent model queries to the current tenant', function () {
    seedIsolationData($this);

    expect(RecommendationEvent::count())->toBe(2);

    $this->resolver->setTenantId('tenant-2');
    expect(RecommendationEvent::count())->toBe(2);
});

it('scopes the recommendations relation of a model', function () {
    $data = seedIsolationData($this);
    $a1 = $data['a1'];
    $a2 = $data['a2'];

    expect($a1->recommendations()->pluck('recommended_id'))->toContain($a2->getKey());
    expect($a1->recommendations()->count())->toBe(1);
});

it('never returns another tenants data from recommend()', function () {
    $data = seedIsolationData($this);
    $a1 = $data['a1'];
    $a2 = $data['a2'];
    $b2 = $data['b2'];

    $ids = $a1->recommend()->map->getKey();

    expect($ids)->toContain($a2->getKey());
    expect($ids)->not->toContain($b2->getKey());

    $this->resolver->setTenantId('tenant-2');
    $b1 = $data['b1'];
    $idsB = $b1->recommend()->map->getKey();

    expect($idsB)->toContain($b2->getKey());
    expect($idsB)->not->toContain($a2->getKey());
});

it('scopes the co-occurrence strategy to the current tenant', function () {
    $data = seedIsolationData($this);
    $a1 = $data['a1'];
    $a2 = $data['a2'];
    $b2 = $data['b2'];

    $strategy = new CoOccurrenceStrategy;
    $resultA = $strategy->compute(Product::find($a1->getKey()), 10);

    expect(collect($resultA)->pluck('recommended_id'))->toContain($a2->getKey());
    expect(collect($resultA)->pluck('recommended_id'))->not->toContain($b2->getKey());

    $this->resolver->setTenantId('tenant-2');
    $b1 = $data['b1'];
    $resultB = $strategy->compute(Product::find($b1->getKey()), 10);

    expect(collect($resultB)->pluck('recommended_id'))->toContain($b2->getKey());
    expect(collect($resultB)->pluck('recommended_id'))->not->toContain($a2->getKey());
});

it('does not leak tenant ids through recommendation_settings', function () {
    RecommendationSetting::query()->create([
        'tenant_id' => 'tenant-1',
        'model' => Product::class,
        'strategy' => 'A',
    ]);
    RecommendationSetting::query()->create([
        'tenant_id' => 'tenant-2',
        'model' => Product::class,
        'strategy' => 'B',
    ]);

    $this->resolver->setTenantId('tenant-1');

    $ids = RecommendationSetting::pluck('tenant_id');

    expect($ids)->toHaveCount(1);
    expect($ids->first())->toBe('tenant-1');
});

it('resolves the tenant-specific strategy for the current tenant', function () {
    RecommendationSetting::query()->create([
        'tenant_id' => 'tenant-1',
        'model' => Product::class,
        'strategy' => 'TenantOneStrategy',
    ]);
    RecommendationSetting::query()->create([
        'tenant_id' => 'tenant-2',
        'model' => Product::class,
        'strategy' => 'TenantTwoStrategy',
    ]);

    $this->resolver->setTenantId('tenant-1');
    $product = Product::create(['name' => 'A']);

    expect(RecommendationSetting::strategyFor(Product::class, 'tenant-1'))->toBe('TenantOneStrategy');
    expect(RecommendationSetting::strategyFor(Product::class, 'tenant-2'))->toBe('TenantTwoStrategy');
});
