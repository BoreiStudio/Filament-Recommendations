<?php

use BoreiStudio\FilamentRecommendations\Contracts\TenantResolver;
use BoreiStudio\FilamentRecommendations\Strategies\CoOccurrenceStrategy;
use BoreiStudio\FilamentRecommendations\Tests\Fixtures\FakeTenantResolver;
use BoreiStudio\FilamentRecommendations\Tests\Fixtures\Product;

beforeEach(function () {
    $this->resolver = new FakeTenantResolver;
    app()->instance(TenantResolver::class, $this->resolver);

    $this->strategy = new CoOccurrenceStrategy;
});

function setTenant(object $test, ?string $tenant): void
{
    if (config('recommendations.tenancy.enabled')) {
        $test->resolver->setTenantId($tenant);
    }
}

function createProducts(array $names): array
{
    return collect($names)->map(fn (string $name) => Product::create(['name' => $name]))->all();
}

function recordInteractions(Product $subject, array $related, string $action = 'viewed'): void
{
    foreach ($related as $product) {
        $subject->recordInteraction($action, $product);
    }
}

it('returns the expected ranking for a known co-occurrence pattern', function () {
    setTenant($this, 'tenant-1');

    [$a, $b, $c, $d] = createProducts(['A', 'B', 'C', 'D']);

    // B and C co-occur with A the same number of times; D never co-occurs.
    recordInteractions($a, [$b, $c]);
    recordInteractions($b, [$a]);
    recordInteractions($c, [$a]);
    recordInteractions($a, [$b, $c, $b]); // extra weight to B

    $result = $this->strategy->compute($a, 10);

    $ranking = collect($result)->mapWithKeys(fn ($r) => [
        $r['recommended_id'] => $r['score'],
    ]);

    expect($ranking)->toHaveCount(2);
    expect($ranking->has($b->getKey()))->toBeTrue();
    expect($ranking->has($c->getKey()))->toBeTrue();

    // B co-occurs more than C, so B ranks first and is normalized to 1.
    expect($ranking[$b->getKey()])->toBe(1.0);
    expect($ranking[$c->getKey()])->toBeLessThan(1.0);
});

it('respects the limit', function () {
    setTenant($this, 'tenant-1');

    [$a, $b, $c] = createProducts(['A', 'B', 'C']);

    recordInteractions($a, [$b, $c]);
    recordInteractions($b, [$a]);
    recordInteractions($c, [$a]);

    $result = $this->strategy->compute($a, 1);

    expect($result)->toHaveCount(1);
    expect($result[0]['recommended_id'])->toBe($b->getKey());
});

it('excludes the subject itself', function () {
    setTenant($this, 'tenant-1');

    [$a] = createProducts(['A']);

    $a->recordInteraction('viewed', $a);

    $result = $this->strategy->compute($a, 10);

    expect($result)->toBeEmpty();
});

it('returns an empty result when there are no events', function () {
    setTenant($this, 'tenant-1');

    [$a] = createProducts(['A']);

    expect($this->strategy->compute($a))->toBeEmpty();
});

it('never leaks co-occurrence data between tenants', function () {
    if (! $this->tenancyEnabled()) {
        $this->markTestSkipped('Only meaningful in multi-tenant mode.');
    }

    $this->resolver->setTenantId('tenant-1');
    [$a1, $a2] = createProducts(['A1', 'A2']);
    recordInteractions($a1, [$a2]);

    $this->resolver->setTenantId('tenant-2');
    [$b1, $b2, $b3] = createProducts(['B1', 'B2', 'B3']);
    recordInteractions($b1, [$b2, $b3]);

    // Tenant 1 only sees its own recommendations.
    $this->resolver->setTenantId('tenant-1');
    $resultA = $this->strategy->compute($a1, 10);

    expect(collect($resultA)->pluck('recommended_id'))->toContain($a2->getKey());
    expect(collect($resultA)->pluck('recommended_id'))->not->toContain($b2->getKey());
    expect(collect($resultA)->pluck('recommended_id'))->not->toContain($b3->getKey());

    // Tenant 2 only sees its own.
    $this->resolver->setTenantId('tenant-2');
    $resultB = $this->strategy->compute($b1, 10);

    expect(collect($resultB)->pluck('recommended_id'))->toContain($b2->getKey());
    expect(collect($resultB)->pluck('recommended_id'))->toContain($b3->getKey());
    expect(collect($resultB)->pluck('recommended_id'))->not->toContain($a2->getKey());
});

it('returns empty when multi-tenant is enabled but no tenant is resolved', function () {
    if (! $this->tenancyEnabled()) {
        $this->markTestSkipped('Only meaningful in multi-tenant mode.');
    }

    [$a, $b] = createProducts(['A', 'B']);
    recordInteractions($a, [$b]);

    // No tenant context -> must not compute across tenants.
    expect($this->strategy->compute($a, 10))->toBeEmpty();
});
