<?php

use BoreiStudio\FilamentRecommendations\Contracts\TenantResolver;
use BoreiStudio\FilamentRecommendations\Models\RecommendationEvent;
use BoreiStudio\FilamentRecommendations\Tests\Fixtures\FakeTenantResolver;
use BoreiStudio\FilamentRecommendations\Tests\Fixtures\Product;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->resolver = new FakeTenantResolver;
    app()->instance(TenantResolver::class, $this->resolver);
});

function createEventWithAge(Product $product, ?Product $related, int $daysAgo): RecommendationEvent
{
    $event = $product->recordInteraction('viewed', $related);

    DB::table('recommendation_events')
        ->where('id', $event->getKey())
        ->update(['created_at' => now()->subDays($daysAgo)]);

    return $event;
}

it('prunes events older than the retention policy', function () {
    $product = Product::create(['name' => 'A']);
    $related = Product::create(['name' => 'B']);

    createEventWithAge($product, $related, 200);
    createEventWithAge($product, $related, 10);

    $this->artisan('recommendations:prune-events')->assertSuccessful();

    expect(RecommendationEvent::count())->toBe(1);
});

it('keeps events within the retention window by default', function () {
    $product = Product::create(['name' => 'A']);
    $related = Product::create(['name' => 'B']);

    createEventWithAge($product, $related, 30);
    createEventWithAge($product, $related, 60);

    $this->artisan('recommendations:prune-events')->assertSuccessful();

    expect(RecommendationEvent::count())->toBe(2);
});

it('honours the --days option', function () {
    $product = Product::create(['name' => 'A']);
    $related = Product::create(['name' => 'B']);

    createEventWithAge($product, $related, 200);
    createEventWithAge($product, $related, 30);

    $this->artisan('recommendations:prune-events', ['--days' => 365])->assertSuccessful();

    expect(RecommendationEvent::count())->toBe(2);
});

it('only prunes events for the given tenant', function () {
    if (! $this->tenancyEnabled()) {
        $this->markTestSkipped('Only meaningful in multi-tenant mode.');
    }

    $this->resolver->setTenantId('tenant-1');
    $a = Product::create(['name' => 'A']);
    $b = Product::create(['name' => 'B']);
    createEventWithAge($a, $b, 200);

    $this->resolver->setTenantId('tenant-2');
    $c = Product::create(['name' => 'C']);
    $d = Product::create(['name' => 'D']);
    createEventWithAge($c, $d, 200);

    $this->artisan('recommendations:prune-events', ['--tenant' => 'tenant-1'])->assertSuccessful();

    expect(RecommendationEvent::count())->toBe(1);
    expect(RecommendationEvent::first()->tenant_id)->toBe('tenant-2');
});
