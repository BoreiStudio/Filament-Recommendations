<?php

use BoreiStudio\FilamentRecommendations\Contracts\TenantResolver;
use BoreiStudio\FilamentRecommendations\Models\RecommendationSetting;
use BoreiStudio\FilamentRecommendations\Pages\RecommendationSettingsPage;
use BoreiStudio\FilamentRecommendations\Tests\Fixtures\FakeTenantResolver;
use BoreiStudio\FilamentRecommendations\Tests\Fixtures\Product;

beforeEach(function () {
    $this->resolver = new FakeTenantResolver;
    app()->instance(TenantResolver::class, $this->resolver);

    config()->set('recommendations.models', [Product::class]);
});

it('saves settings scoped to the current tenant', function () {
    if ($this->tenancyEnabled()) {
        $this->resolver->setTenantId('tenant-1');
    }

    $page = new RecommendationSettingsPage;
    $page->mount();

    $page->data = [
        'tenant_id' => $this->tenancyEnabled() ? 'tenant-1' : null,
        'settings' => [
            [
                'model' => Product::class,
                'strategy' => 'SomeStrategy',
                'limit' => 5,
            ],
        ],
    ];

    $page->save();

    $saved = RecommendationSetting::query()
        ->withoutGlobalScope('recommendation_tenant')
        ->where('model', Product::class)
        ->first();

    expect($saved)->not->toBeNull();
    expect($saved->strategy)->toBe('SomeStrategy');
    expect($saved->limit)->toBe(5);
    expect($saved->tenant_id)->toBe($this->tenancyEnabled() ? 'tenant-1' : null);
});

it('does not leak settings between tenants', function () {
    if (! $this->tenancyEnabled()) {
        $this->markTestSkipped('Only meaningful in multi-tenant mode.');
    }

    $this->resolver->setTenantId('tenant-1');
    $page = new RecommendationSettingsPage;
    $page->mount();
    $page->data = [
        'tenant_id' => 'tenant-1',
        'settings' => [['model' => Product::class, 'strategy' => 'Strategy-A', 'limit' => 3]],
    ];
    $page->save();

    $this->resolver->setTenantId('tenant-2');
    $pageTwo = new RecommendationSettingsPage;
    $pageTwo->mount();
    $pageTwo->data = [
        'tenant_id' => 'tenant-2',
        'settings' => [['model' => Product::class, 'strategy' => 'Strategy-B', 'limit' => 7]],
    ];
    $pageTwo->save();

    expect(RecommendationSetting::strategyFor(Product::class, 'tenant-1'))->toBe('Strategy-A');
    expect(RecommendationSetting::strategyFor(Product::class, 'tenant-2'))->toBe('Strategy-B');
});

it('exposes the available strategies', function () {
    config()->set('recommendations.strategies.registry', ['SomeStrategyA', 'SomeStrategyB']);

    $page = new RecommendationSettingsPage;

    $strategies = $page->getStrategies();

    expect($strategies)->toHaveKey('SomeStrategyA');
    expect($strategies)->toHaveKey('SomeStrategyB');
});

it('reports the last rebuild time', function () {
    $product = Product::create(['name' => 'A']);
    $related = Product::create(['name' => 'B']);

    $product->recommendations()->create([
        'recommended_type' => $related->getMorphClass(),
        'recommended_id' => $related->getKey(),
        'score' => 1.0,
        'strategy' => 'co-occurrence',
        'computed_at' => now()->subHour(),
    ]);

    $page = new RecommendationSettingsPage;

    expect($page->getLastRebuildAt())->not->toBeNull();
});

it('returns null when nothing was rebuilt yet', function () {
    $page = new RecommendationSettingsPage;

    expect($page->getLastRebuildAt())->toBeNull();
});
