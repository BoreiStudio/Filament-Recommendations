<?php

use BoreiStudio\FilamentRecommendations\Contracts\TenantResolver;
use BoreiStudio\FilamentRecommendations\Models\RecommendationEvent;
use BoreiStudio\FilamentRecommendations\Support\RecommendationService;
use BoreiStudio\FilamentRecommendations\Tests\Fixtures\FakeTenantResolver;
use BoreiStudio\FilamentRecommendations\Tests\Fixtures\Product;

beforeEach(function () {
    $this->resolver = new FakeTenantResolver;
    app()->instance(TenantResolver::class, $this->resolver);

    $this->service = app(RecommendationService::class);

    if (config('recommendations.tenancy.enabled')) {
        $this->resolver->setTenantId('tenant-1');
    }
});

it('records an interaction for any model', function () {
    $product = Product::create(['name' => 'A']);
    $related = Product::create(['name' => 'B']);

    $event = $this->service->record($product, 'viewed', $related);

    expect($event)->toBeInstanceOf(RecommendationEvent::class);
    expect($event->action)->toBe('viewed');
    expect($event->subject_id)->toBe($product->getKey());
    expect($event->related_id)->toBe($related->getKey());
});

it('reads recommendations for a model without the trait', function () {
    $product = Product::create(['name' => 'A']);
    $recommended = Product::create(['name' => 'B']);

    $product->recommendations()->create([
        'recommended_type' => $recommended->getMorphClass(),
        'recommended_id' => $recommended->getKey(),
        'score' => 1.0,
        'strategy' => 'co-occurrence',
    ]);

    $results = $this->service->for($product, 10);

    expect($results)->toHaveCount(1);
    expect($results->first()->getKey())->toBe($recommended->getKey());
});

it('respects the tenant scope when reading recommendations', function () {
    if (! $this->tenancyEnabled()) {
        $this->markTestSkipped('Only meaningful in multi-tenant mode.');
    }

    $this->resolver->setTenantId('tenant-1');
    $a = Product::create(['name' => 'A']);
    $aRec = Product::create(['name' => 'A rec']);
    $a->recommendations()->create([
        'recommended_type' => $aRec->getMorphClass(),
        'recommended_id' => $aRec->getKey(),
        'score' => 1.0,
        'strategy' => 'co-occurrence',
    ]);

    $this->resolver->setTenantId('tenant-2');
    $b = Product::create(['name' => 'B']);
    $bRec = Product::create(['name' => 'B rec']);
    $b->recommendations()->create([
        'recommended_type' => $bRec->getMorphClass(),
        'recommended_id' => $bRec->getKey(),
        'score' => 1.0,
        'strategy' => 'co-occurrence',
    ]);

    $this->resolver->setTenantId('tenant-1');

    $results = $this->service->for($a, 10);

    expect($results->pluck('name'))->toContain('A rec');
    expect($results->pluck('name'))->not->toContain('B rec');
});
