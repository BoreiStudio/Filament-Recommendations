<?php

use BoreiStudio\FilamentRecommendations\Contracts\TenantResolver;
use BoreiStudio\FilamentRecommendations\Models\Recommendation;
use BoreiStudio\FilamentRecommendations\Models\RecommendationEvent;
use BoreiStudio\FilamentRecommendations\Tests\Fixtures\FakeTenantResolver;
use BoreiStudio\FilamentRecommendations\Tests\Fixtures\Product;

beforeEach(function () {
    $this->resolver = new FakeTenantResolver;
    app()->instance(TenantResolver::class, $this->resolver);
});

it('recommend returns the cached recommendations sorted by score', function () {
    $product = Product::create(['name' => 'Product A']);
    $recommended = Product::create(['name' => 'Product B']);

    $product->recommendations()->create([
        'recommended_type' => $recommended->getMorphClass(),
        'recommended_id' => $recommended->getKey(),
        'score' => 0.8,
        'strategy' => 'co-occurrence',
    ]);

    $product->recommendations()->create([
        'recommended_type' => $recommended->getMorphClass(),
        'recommended_id' => $recommended->getKey(),
        'score' => 0.9,
        'strategy' => 'content-based',
    ]);

    $ids = $product->recommend()->map(fn ($model) => $model->getKey());

    expect($ids->count())->toBe(1);
    expect($ids->first())->toBe($recommended->getKey());
});

it('respects the limit argument', function () {
    $product = Product::create(['name' => 'A']);
    $others = Product::create(['name' => 'B']);
    $more = Product::create(['name' => 'C']);

    foreach ([$others, $more] as $other) {
        $product->recommendations()->create([
            'recommended_type' => $other->getMorphClass(),
            'recommended_id' => $other->getKey(),
            'score' => 0.5,
            'strategy' => 'test',
        ]);
    }

    expect($product->recommend(1)->count())->toBe(1);
    expect($product->recommend()->count())->toBe(2);
});

it('recordInteraction logs an event with the resolved tenant id', function () {
    if ($this->tenancyEnabled()) {
        $this->resolver->setTenantId(42);
    }

    $product = Product::create(['name' => 'A']);
    $related = Product::create(['name' => 'B']);

    $event = $product->recordInteraction('viewed', $related);

    expect($event)->toBeInstanceOf(RecommendationEvent::class);
    expect($event->action)->toBe('viewed');
    expect($event->subject_id)->toBe($product->getKey());
    expect($event->related_id)->toBe($related->getKey());
    expect($event->tenant_id)->toBe($this->tenancyEnabled() ? '42' : null);
});

it('recordInteraction works without a related context', function () {
    $product = Product::create(['name' => 'A']);

    $event = $product->recordInteraction('viewed');

    expect($event->related_id)->toBeNull();
    expect($event->related_type)->toBeNull();
});

it('getRecommendationTenantId delegates to the tenant resolver', function () {
    if ($this->tenancyEnabled()) {
        $this->resolver->setTenantId('tenant-1');
    }

    $product = Product::create(['name' => 'A']);

    expect($product->getRecommendationTenantId())->toBe($this->tenancyEnabled() ? 'tenant-1' : null);
});

it('isolates recommendations between tenants when multi-tenant is enabled', function () {
    if (! $this->tenancyEnabled()) {
        $this->markTestSkipped('Only meaningful in multi-tenant mode.');
    }

    $this->resolver->setTenantId('tenant-1');
    $a = Product::create(['name' => 'A']);
    $aRelated = Product::create(['name' => 'A related']);
    $a->recommendations()->create([
        'recommended_type' => $aRelated->getMorphClass(),
        'recommended_id' => $aRelated->getKey(),
        'score' => 0.9,
        'strategy' => 'co-occurrence',
    ]);

    $this->resolver->setTenantId('tenant-2');
    $b = Product::create(['name' => 'B']);
    $bRelated = Product::create(['name' => 'B related']);
    $b->recommendations()->create([
        'recommended_type' => $bRelated->getMorphClass(),
        'recommended_id' => $bRelated->getKey(),
        'score' => 0.9,
        'strategy' => 'co-occurrence',
    ]);

    $this->resolver->setTenantId('tenant-1');

    $recommendedA = $a->recommend()->pluck('name');

    expect($recommendedA)->toContain('A related');
    expect($recommendedA)->not->toContain('B related');

    $this->resolver->setTenantId('tenant-2');

    $recommendedB = $b->recommend()->pluck('name');

    expect($recommendedB)->toContain('B related');
    expect($recommendedB)->not->toContain('A related');
});

it('isolates cached recommendation queries by tenant', function () {
    if (! $this->tenancyEnabled()) {
        $this->markTestSkipped('Only meaningful in multi-tenant mode.');
    }

    $this->resolver->setTenantId('tenant-1');
    $a = Product::create(['name' => 'A']);
    $aRelated = Product::create(['name' => 'A related']);
    $a->recommendations()->create([
        'recommended_type' => $aRelated->getMorphClass(),
        'recommended_id' => $aRelated->getKey(),
        'score' => 0.9,
        'strategy' => 'co-occurrence',
    ]);

    $this->resolver->setTenantId('tenant-2');
    $b = Product::create(['name' => 'B']);
    $bRelated = Product::create(['name' => 'B related']);
    $b->recommendations()->create([
        'recommended_type' => $bRelated->getMorphClass(),
        'recommended_id' => $bRelated->getKey(),
        'score' => 0.9,
        'strategy' => 'co-occurrence',
    ]);

    $this->resolver->setTenantId('tenant-1');

    expect(Recommendation::count())->toBe(1);
    expect(Recommendation::first()->source_id)->toBe($a->getKey());
});

it('uses a single recommendation row per (tenant, source, recommended, strategy)', function () {
    $product = Product::create(['name' => 'A']);
    $related = Product::create(['name' => 'B']);

    $product->recommendations()->create([
        'recommended_type' => $related->getMorphClass(),
        'recommended_id' => $related->getKey(),
        'score' => 0.5,
        'strategy' => 'co-occurrence',
    ]);

    $product->recommendations()->updateOrCreate(
        [
            'recommended_type' => $related->getMorphClass(),
            'recommended_id' => $related->getKey(),
            'strategy' => 'co-occurrence',
        ],
        ['score' => 0.9],
    );

    expect($product->recommendations()->count())->toBe(1);
    expect($product->recommendations()->first()->score)->toBe(0.9);
});
