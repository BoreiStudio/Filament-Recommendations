<?php

use BoreiStudio\FilamentRecommendations\Contracts\TenantResolver;
use BoreiStudio\FilamentRecommendations\Jobs\RecalculateRecommendationsJob;
use BoreiStudio\FilamentRecommendations\Models\Recommendation;
use BoreiStudio\FilamentRecommendations\Models\RecommendationEvent;
use BoreiStudio\FilamentRecommendations\Strategies\CoOccurrenceStrategy;
use BoreiStudio\FilamentRecommendations\Support\RecommendationService;
use BoreiStudio\FilamentRecommendations\Tests\Fixtures\FakeTenantResolver;
use BoreiStudio\FilamentRecommendations\Tests\Fixtures\PlainProduct;
use BoreiStudio\FilamentRecommendations\Tests\Fixtures\Product;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->resolver = new FakeTenantResolver;
    app()->instance(TenantResolver::class, $this->resolver);
});

function recordSyntheticEvents(Product $subject, array $related, string $action = 'viewed'): void
{
    foreach ($related as $product) {
        $subject->recordInteraction($action, $product);
        $product->recordInteraction($action, $subject);
    }
}

it('dispatches recalculation jobs on interaction by default', function () {
    Queue::fake();

    $product = Product::create(['name' => 'A']);
    $related = Product::create(['name' => 'B']);

    $product->recordInteraction('viewed', $related);

    Queue::assertPushed(RecalculateRecommendationsJob::class, 2);
});

it('does not dispatch jobs on interaction when disabled', function () {
    config()->set('recommendations.events.recalculate_on_interaction', false);

    Queue::fake();

    $product = Product::create(['name' => 'A']);

    $product->recordInteraction('viewed');

    Queue::assertNotPushed(RecalculateRecommendationsJob::class);
});

it('runs the strategy and upserts recommendations for the subject', function () {
    $this->resolver->setTenantId('tenant-1');

    $product = Product::create(['name' => 'A']);
    $recommended = Product::create(['name' => 'B']);

    recordSyntheticEvents($product, [$recommended]);

    (new RecalculateRecommendationsJob($product))->handle();

    $row = Recommendation::query()
        ->where('source_id', $product->getKey())
        ->where('recommended_id', $recommended->getKey())
        ->first();

    expect($row)->not->toBeNull();
    expect((float) $row->score)->toBe(1.0);
    expect($row->strategy)->toBe(CoOccurrenceStrategy::class);
    expect($row->tenant_id)->toBe('tenant-1');
});

it('replaces stale recommendations when rerun', function () {
    $this->resolver->setTenantId('tenant-1');

    $product = Product::create(['name' => 'A']);
    $first = Product::create(['name' => 'B']);
    $second = Product::create(['name' => 'C']);

    recordSyntheticEvents($product, [$first, $second]);

    (new RecalculateRecommendationsJob($product))->handle();
    expect($product->recommendations()->count())->toBe(2);

    // The co-occurrence for the first item disappears...
    RecommendationEvent::query()
        ->where(fn ($q) => $q->where('related_id', $first->getKey())->orWhere('subject_id', $first->getKey()))
        ->delete();

    (new RecalculateRecommendationsJob($product))->handle();

    expect($product->recommendations()->count())->toBe(1);
    expect($product->recommendations()->first()->recommended_id)->toBe($second->getKey());
});

it('processes a subject without the trait via the recommendation service', function () {
    $this->resolver->setTenantId('tenant-1');

    $plain = PlainProduct::create(['name' => 'A']);
    $related = Product::create(['name' => 'B']);

    // Register co-occurrence events against the plain model using the service.
    $service = app(RecommendationService::class);
    $service->record($plain, 'viewed', $related);
    $service->record($related, 'viewed', $plain);

    (new RecalculateRecommendationsJob($plain))->handle();

    $rows = Recommendation::query()
        ->where('source_type', $plain->getMorphClass())
        ->where('source_id', $plain->getKey())
        ->get();

    expect($rows)->toHaveCount(1);
    expect($rows->first()->recommended_id)->toBe($related->getKey());
});
