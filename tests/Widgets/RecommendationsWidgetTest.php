<?php

use BoreiStudio\FilamentRecommendations\Contracts\TenantResolver;
use BoreiStudio\FilamentRecommendations\Tests\Fixtures\FakeTenantResolver;
use BoreiStudio\FilamentRecommendations\Tests\Fixtures\Product;
use BoreiStudio\FilamentRecommendations\Widgets\RecommendationsWidget;

beforeEach(function () {
    $this->resolver = new FakeTenantResolver;
    app()->instance(TenantResolver::class, $this->resolver);

    if (config('recommendations.tenancy.enabled')) {
        $this->resolver->setTenantId('tenant-1');
    }
});

it('exposes the recommendations for the given record', function () {
    $product = Product::create(['name' => 'Product A']);
    $recommended = Product::create(['name' => 'Product B']);

    $product->recommendations()->create([
        'recommended_type' => $recommended->getMorphClass(),
        'recommended_id' => $recommended->getKey(),
        'score' => 1.0,
        'strategy' => 'co-occurrence',
    ]);

    $widget = new RecommendationsWidget;
    $widget->record = $product;

    $titles = $widget->getRecommendations()->map(fn ($model) => $widget->getRecommendationTitle($model));

    expect($titles)->toContain('Product B');
    expect($titles)->not->toContain('Product A');
});

it('returns an empty collection when there are no recommendations', function () {
    $product = Product::create(['name' => 'Product A']);

    $widget = new RecommendationsWidget;
    $widget->record = $product;

    expect($widget->getRecommendations())->toBeEmpty();
    expect($widget->hasRecommendations())->toBeFalse();
});

it('returns recommendations scoped to the current tenant', function () {
    if (! $this->tenancyEnabled()) {
        $this->markTestSkipped('Only meaningful in multi-tenant mode.');
    }

    $product = Product::create(['name' => 'A']);
    $own = Product::create(['name' => 'own']);
    $other = Product::create(['name' => 'other']);

    $this->resolver->setTenantId('tenant-1');
    $product->recommendations()->create([
        'recommended_type' => $own->getMorphClass(),
        'recommended_id' => $own->getKey(),
        'score' => 1.0,
        'strategy' => 'co-occurrence',
    ]);

    $this->resolver->setTenantId('tenant-2');
    $other->recommendations()->create([
        'recommended_type' => $own->getMorphClass(),
        'recommended_id' => $own->getKey(),
        'score' => 1.0,
        'strategy' => 'co-occurrence',
    ]);

    $this->resolver->setTenantId('tenant-1');

    $widget = new RecommendationsWidget;
    $widget->record = $product;

    $ids = $widget->getRecommendations()->map->getKey();

    expect($ids)->toHaveCount(1);
    expect($ids->first())->toBe($own->getKey());
});

it('returns an empty collection when no record is set', function () {
    $widget = new RecommendationsWidget;

    expect($widget->getRecommendations())->toBeEmpty();
});
