<?php

use BoreiStudio\FilamentRecommendations\Contracts\TenantResolver;
use BoreiStudio\FilamentRecommendations\Exceptions\MissingRecommendationsTable;
use BoreiStudio\FilamentRecommendations\Exceptions\TenantResolverNotConfigured;
use BoreiStudio\FilamentRecommendations\Resolvers\DefaultTenantResolver;
use BoreiStudio\FilamentRecommendations\Strategies\ContentBasedStrategy;
use BoreiStudio\FilamentRecommendations\Strategies\CoOccurrenceStrategy;
use BoreiStudio\FilamentRecommendations\Support\RecommendationsGuard;
use BoreiStudio\FilamentRecommendations\Support\StrategyResolver;
use BoreiStudio\FilamentRecommendations\Tests\Fixtures\FakeTenantResolver;
use BoreiStudio\FilamentRecommendations\Tests\Fixtures\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

it('accepts any Eloquent model as a co-occurrence subject (no trait required)', function () {
    app()->instance(TenantResolver::class, new FakeTenantResolver);

    $strategy = new CoOccurrenceStrategy;

    expect($strategy->compute(new class extends Model {}))->toBe([]);
});

it('throws a clear error for an unknown strategy', function () {
    (new StrategyResolver)->resolve('NonExistent\\Strategy\\Class');
})->throws(InvalidArgumentException::class, 'Unknown recommendation strategy');

it('throws a clear error when multi-tenant mode has no configured resolver', function () {
    config()->set('recommendations.tenancy.enabled', true);
    config()->set('recommendations.tenancy.resolver', null);

    (new DefaultTenantResolver)->resolveTenantId();
})->throws(TenantResolverNotConfigured::class, 'no TenantResolver is configured');

it('does not throw when tenancy is disabled without a resolver', function () {
    config()->set('recommendations.tenancy.enabled', false);
    config()->set('recommendations.tenancy.resolver', null);

    expect((new DefaultTenantResolver)->resolveTenantId())->toBeNull();
});

it('throws a clear error when the tables are not migrated', function () {
    Schema::drop('recommendations');

    RecommendationsGuard::assertReady();
})->throws(MissingRecommendationsTable::class, 'does not exist');

it('throws a clear error when content-based gets a model without features', function () {
    $strategy = new ContentBasedStrategy;

    expect($strategy->compute(new class extends Model {}))->toBe([]);
});

it('accepts a recommendable product as strategy subject', function () {
    app()->instance(
        TenantResolver::class,
        new FakeTenantResolver,
    );

    $product = Product::create(['name' => 'A']);

    $strategy = new CoOccurrenceStrategy;

    expect($strategy->compute($product, 10))->toBe([]);
});
