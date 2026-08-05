<?php

use BoreiStudio\FilamentRecommendations\Contracts\TenantResolver;
use BoreiStudio\FilamentRecommendations\Strategies\ContentBasedStrategy;
use BoreiStudio\FilamentRecommendations\Tests\Fixtures\Article;
use BoreiStudio\FilamentRecommendations\Tests\Fixtures\FakeTenantResolver;
use BoreiStudio\FilamentRecommendations\Tests\Fixtures\Product;

beforeEach(function () {
    $this->resolver = new FakeTenantResolver;
    app()->instance(TenantResolver::class, $this->resolver);

    $this->strategy = new ContentBasedStrategy;
});

function createProductsWithFeatures(array $products): array
{
    return collect($products)->map(fn (array $attributes) => Product::create($attributes))->all();
}

it('recommends identical models with the maximum score', function () {
    $products = createProductsWithFeatures([
        ['name' => 'A', 'category' => 'electronics', 'brand' => 'acme', 'price' => 100, 'tags' => ['new', 'gadget']],
        ['name' => 'B', 'category' => 'electronics', 'brand' => 'acme', 'price' => 100, 'tags' => ['new', 'gadget']],
    ]);

    [$a, $b] = $products;

    $result = $this->strategy->compute($a, 10);

    expect($result)->toHaveCount(1);
    expect($result[0]['recommended_id'])->toBe($b->getKey());
    expect($result[0]['score'])->toBe(1.0);
});

it('gives a score close to zero for completely disjoint attributes', function () {
    $products = createProductsWithFeatures([
        ['name' => 'A', 'category' => 'electronics', 'brand' => 'acme', 'price' => 100, 'tags' => ['gadget']],
        ['name' => 'B', 'category' => 'books', 'brand' => 'penguin', 'price' => 10, 'tags' => ['paperback']],
    ]);

    [$a, $b] = $products;

    $result = $this->strategy->compute($a, 10);

    expect($result)->toHaveCount(1);
    expect($result[0]['score'])->toBeLessThan(0.1);
});

it('ranks the most similar model first', function () {
    $products = createProductsWithFeatures([
        ['name' => 'A', 'category' => 'electronics', 'brand' => 'acme', 'price' => 100, 'tags' => ['new', 'gadget']],
        ['name' => 'Similar', 'category' => 'electronics', 'brand' => 'acme', 'price' => 99, 'tags' => ['new', 'gadget']],
        ['name' => 'Meh', 'category' => 'electronics', 'brand' => 'other', 'price' => 500, 'tags' => ['used']],
        ['name' => 'Unrelated', 'category' => 'books', 'brand' => 'penguin', 'price' => 10, 'tags' => ['paperback']],
    ]);

    [$a, $similar, $meh, $unrelated] = $products;

    $result = $this->strategy->compute($a, 10);

    $ids = collect($result)->pluck('recommended_id');

    expect($ids->first())->toBe($similar->getKey());
    expect($ids)->toContain($meh->getKey());
    expect($ids)->toContain($unrelated->getKey());
});

it('respects the limit', function () {
    $products = createProductsWithFeatures([
        ['name' => 'A', 'category' => 'electronics', 'price' => 100],
        ['name' => 'B', 'category' => 'electronics', 'price' => 100],
        ['name' => 'C', 'category' => 'electronics', 'price' => 100],
    ]);

    [$a] = $products;

    $result = $this->strategy->compute($a, 1);

    expect($result)->toHaveCount(1);
});

it('supports per-attribute weights', function () {
    config()->set('recommendations.strategies.weights.'.Product::class, [
        'category' => 5,
        'price' => 0,
    ]);

    $products = createProductsWithFeatures([
        ['name' => 'A', 'category' => 'electronics', 'price' => 100],
        // Same category but very different price.
        ['name' => 'SameCat', 'category' => 'electronics', 'price' => 9999],
        // Different category but same price.
        ['name' => 'SamePrice', 'category' => 'books', 'price' => 100],
    ]);

    [$a] = $products;

    $result = $this->strategy->compute($a, 10);

    // With category weighted high, the same-category model must rank first
    // even though its price is very different.
    expect($result[0]['recommended_id'])->toBe($products[1]->getKey());
});

it('never mixes data between tenants', function () {
    if (! $this->tenancyEnabled()) {
        $this->markTestSkipped('Only meaningful in multi-tenant mode.');
    }

    Article::create([
        'name' => 'A1',
        'category' => 'electronics',
        'price' => 100,
        'tenant_id' => 'tenant-1',
    ]);
    Article::create([
        'name' => 'A2',
        'category' => 'electronics',
        'price' => 100,
        'tenant_id' => 'tenant-1',
    ]);

    Article::create([
        'name' => 'B1',
        'category' => 'electronics',
        'price' => 100,
        'tenant_id' => 'tenant-2',
    ]);
    Article::create([
        'name' => 'B2',
        'category' => 'electronics',
        'price' => 100,
        'tenant_id' => 'tenant-2',
    ]);

    $a1 = Article::where('tenant_id', 'tenant-1')->where('name', 'A1')->first();

    $result = $this->strategy->compute($a1, 10);

    $tenant1Ids = Article::where('tenant_id', 'tenant-1')->pluck('id');
    $ids = collect($result)->pluck('recommended_id');

    expect($ids->isEmpty())->toBeFalse();
    expect($ids->intersect($tenant1Ids)->count())->toBe($ids->count());
});
