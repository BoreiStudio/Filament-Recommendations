<?php

use BoreiStudio\FilamentRecommendations\Contracts\TenantResolver;
use BoreiStudio\FilamentRecommendations\Jobs\RecalculateRecommendationsJob;
use BoreiStudio\FilamentRecommendations\Support\RecommendationCache;
use BoreiStudio\FilamentRecommendations\Tests\Fixtures\FakeTenantResolver;
use BoreiStudio\FilamentRecommendations\Tests\Fixtures\Product;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->resolver = new FakeTenantResolver;
    app()->instance(TenantResolver::class, $this->resolver);

    if (config('recommendations.tenancy.enabled')) {
        $this->resolver->setTenantId('tenant-1');
    }
});

it('closes the loop: interaction → rebuild job → recommend() returns items', function () {
    config()->set('recommendations.events.recalculate_on_interaction', false);

    $a = Product::create(['name' => 'A']);
    $b = Product::create(['name' => 'B']);
    $c = Product::create(['name' => 'C']);

    // Users who viewed A also viewed B (twice) and C (once).
    $a->recordInteraction('viewed', $b);
    $b->recordInteraction('viewed', $a);
    $a->recordInteraction('viewed', $b);
    $b->recordInteraction('viewed', $a);
    $a->recordInteraction('viewed', $c);
    $c->recordInteraction('viewed', $a);

    // Nothing is computed yet: recommend() is empty until a rebuild runs.
    expect($a->recommend())->toBeEmpty();

    // Running the job (e.g. from recommendations:rebuild) fills the table.
    (new RecalculateRecommendationsJob($a))->handle();

    $results = $a->recommend();

    expect($results->pluck('name'))->toContain('B');
    expect($results->pluck('name'))->toContain('C');
    expect($results->first()->getKey())->toBe($b->getKey()); // B co-occurs more
});

it('caches recommendations and serves them without recomputing', function () {
    config()->set('recommendations.events.recalculate_on_interaction', false);

    $a = Product::create(['name' => 'A']);
    $b = Product::create(['name' => 'B']);

    $a->recordInteraction('viewed', $b);
    $b->recordInteraction('viewed', $a);

    (new RecalculateRecommendationsJob($a))->handle();

    expect($a->recommend()->pluck('name'))->toContain('B');

    // Simulate a second request: remove the row to prove the cache serves it.
    DB::table('recommendations')->delete();

    expect($a->recommend()->pluck('name'))->toContain('B');

    // Invalidating the cache exposes the empty state again.
    RecommendationCache::forget($a, $this->resolver->resolveTenantId());
    expect($a->recommend())->toBeEmpty();
});

it('dispatches a rebuild job on interaction with the default config', function () {
    Queue::fake();

    $a = Product::create(['name' => 'A']);
    $b = Product::create(['name' => 'B']);

    $a->recordInteraction('viewed', $b);

    Queue::assertPushed(RecalculateRecommendationsJob::class, 2);
});
