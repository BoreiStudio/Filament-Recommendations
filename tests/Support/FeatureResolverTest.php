<?php

use BoreiStudio\FilamentRecommendations\Contracts\FeatureProvider;
use BoreiStudio\FilamentRecommendations\Strategies\ContentBasedStrategy;
use BoreiStudio\FilamentRecommendations\Support\FeatureResolver;
use BoreiStudio\FilamentRecommendations\Tests\Fixtures\PlainProduct;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    app()->instance(
        FeatureProvider::class,
        new class implements FeatureProvider
        {
            public function features(Model $model): array
            {
                return [
                    'category' => $model->category,
                    'price' => (float) $model->price,
                ];
            }

            public function supports(Model $model): bool
            {
                return $model instanceof PlainProduct;
            }
        },
    );
});

it('exposes features for a model without the trait via the provider', function () {
    $a = PlainProduct::create(['name' => 'A', 'category' => 'malbec', 'price' => 100]);
    $b = PlainProduct::create(['name' => 'B', 'category' => 'malbec', 'price' => 105]);

    $resolver = app(FeatureResolver::class);

    expect($resolver->supports($a))->toBeTrue();
    expect($resolver->featuresFor($a))->toBe(['category' => 'malbec', 'price' => 100.0]);
});

it('content-based recommends similar products without the trait', function () {
    $a = PlainProduct::create(['name' => 'A', 'category' => 'malbec', 'price' => 100]);
    $b = PlainProduct::create(['name' => 'B', 'category' => 'malbec', 'price' => 105]);
    PlainProduct::create(['name' => 'C', 'category' => 'rose', 'price' => 30]);

    $strategy = new ContentBasedStrategy;
    $results = $strategy->compute($a, 5);

    expect($results)->toHaveCount(2);
    expect($results[0]['recommended_id'])->toBe($b->getKey());
    expect($results[0]['score'])->toBe(1.0);

    $cScore = collect($results)->firstWhere('recommended_id', PlainProduct::where('name', 'C')->first()->getKey())['score'];
    expect($cScore)->toBeLessThan(0.1);
});
