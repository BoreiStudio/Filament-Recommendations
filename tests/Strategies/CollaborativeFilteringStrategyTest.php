<?php

use BoreiStudio\FilamentRecommendations\Contracts\TenantResolver;
use BoreiStudio\FilamentRecommendations\Strategies\CollaborativeFilteringStrategy;
use BoreiStudio\FilamentRecommendations\Tests\Fixtures\FakeTenantResolver;
use BoreiStudio\FilamentRecommendations\Tests\Fixtures\Product;
use BoreiStudio\FilamentRecommendations\Tests\Fixtures\User;

beforeEach(function () {
    $this->resolver = new FakeTenantResolver;
    app()->instance(TenantResolver::class, $this->resolver);

    $this->strategy = new CollaborativeFilteringStrategy;
});

function setCFFixtureTenant(object $test): void
{
    if (config('recommendations.tenancy.enabled')) {
        $test->resolver->setTenantId('tenant-1');
    }
}

function createUsers(array $names): array
{
    return collect($names)->map(fn (string $name) => User::create(['name' => $name]))->all();
}

function recordForUsers(array $users, Product $product, array $related): void
{
    foreach ($users as $user) {
        foreach ($related as $context) {
            $product->recordInteraction('viewed', $context, $user);
        }
    }
}

it('recommends items that share the most users (cosine similarity)', function () {
    setCFFixtureTenant($this);

    $users = createUsers(['U1', 'U2', 'U3', 'U4']);

    [$u1, $u2, $u3, $u4] = $users;

    $products = collect(['A', 'B', 'C', 'D'])
        ->map(fn (string $name) => Product::create(['name' => $name]));

    [$a, $b, $c, $d] = $products;

    // A and B are viewed by the same 3 users (high similarity).
    recordForUsers([$u1, $u2, $u3], $a, [$b]);
    recordForUsers([$u1, $u2, $u3], $b, [$a]);

    // C shares only 1 user with A.
    recordForUsers([$u3], $a, [$c]);
    recordForUsers([$u3], $c, [$a]);

    // D shares no users with A.
    recordForUsers([$u4], $d, [$a]);

    $result = $this->strategy->compute($a, 10);

    $ranking = collect($result)->mapWithKeys(fn ($r) => [$r['recommended_id'] => $r['score']]);

    expect($ranking->has($b->getKey()))->toBeTrue();
    expect($ranking->has($c->getKey()))->toBeTrue();

    // B shares more users with A than C does, so B ranks first.
    expect($ranking[$b->getKey()])->toBe(1.0);
    expect($ranking[$c->getKey()])->toBeLessThan(1.0);
});

it('respects the limit', function () {
    setCFFixtureTenant($this);

    $users = createUsers(['U1', 'U2']);

    $products = collect(['A', 'B', 'C'])
        ->map(fn (string $name) => Product::create(['name' => $name]));

    [$a, $b, $c] = $products;

    recordForUsers($users, $a, [$b, $c]);
    recordForUsers($users, $b, [$a, $c]);
    recordForUsers($users, $c, [$a, $b]);

    $result = $this->strategy->compute($a, 1);

    expect($result)->toHaveCount(1);
});

it('returns an empty result when the subject has no actor interactions', function () {
    setCFFixtureTenant($this);

    $a = Product::create(['name' => 'A']);
    $b = Product::create(['name' => 'B']);

    $a->recordInteraction('viewed', $b);

    expect($this->strategy->compute($a, 10))->toBeEmpty();
});

it('never leaks collaborative data between tenants', function () {
    if (! $this->tenancyEnabled()) {
        $this->markTestSkipped('Only meaningful in multi-tenant mode.');
    }

    $users = createUsers(['U1', 'U2', 'U3']);

    $this->resolver->setTenantId('tenant-1');
    $a1 = Product::create(['name' => 'A1']);
    $a2 = Product::create(['name' => 'A2']);
    recordForUsers([$users[0], $users[1]], $a1, [$a2]);
    recordForUsers([$users[0], $users[1]], $a2, [$a1]);

    $this->resolver->setTenantId('tenant-2');
    $b1 = Product::create(['name' => 'B1']);
    $b2 = Product::create(['name' => 'B2']);
    recordForUsers([$users[0], $users[1], $users[2]], $b1, [$b2]);
    recordForUsers([$users[0], $users[1], $users[2]], $b2, [$b1]);

    $this->resolver->setTenantId('tenant-1');

    $result = $this->strategy->compute($a1, 10);

    $ids = collect($result)->pluck('recommended_id');

    expect($ids)->toContain($a2->getKey());
    expect($ids)->not->toContain($b2->getKey());

    $this->resolver->setTenantId('tenant-2');

    $resultB = $this->strategy->compute($b1, 10);

    $idsB = collect($resultB)->pluck('recommended_id');

    expect($idsB)->toContain($b2->getKey());
    expect($idsB)->not->toContain($a2->getKey());
});
