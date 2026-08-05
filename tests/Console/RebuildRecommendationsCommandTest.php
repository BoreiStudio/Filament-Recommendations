<?php

use BoreiStudio\FilamentRecommendations\Contracts\TenantProvider;
use BoreiStudio\FilamentRecommendations\Contracts\TenantResolver;
use BoreiStudio\FilamentRecommendations\Jobs\RecalculateRecommendationsJob;
use BoreiStudio\FilamentRecommendations\Models\Recommendation;
use BoreiStudio\FilamentRecommendations\Tests\Fixtures\FakeTenantResolver;
use BoreiStudio\FilamentRecommendations\Tests\Fixtures\Product;
use Illuminate\Support\Facades\Queue;

class FakeTenantProvider implements TenantProvider
{
    public function __construct(private array $ids) {}

    public function allTenantIds(): array
    {
        return $this->ids;
    }
}

beforeEach(function () {
    $this->resolver = new FakeTenantResolver;
    app()->instance(TenantResolver::class, $this->resolver);
});

function createProductsSynthetic(array $names): array
{
    return collect($names)->map(fn (string $name) => Product::create(['name' => $name]))->all();
}

it('queues a job per record when a model class is given', function () {
    Queue::fake();

    createProductsSynthetic(['A', 'B', 'C']);

    $this->artisan('recommendations:rebuild', ['model' => Product::class])
        ->assertSuccessful();

    Queue::assertPushed(RecalculateRecommendationsJob::class, 3);
});

it('uses the configured models list when no model is given', function () {
    config()->set('recommendations.models', [Product::class]);

    Queue::fake();

    createProductsSynthetic(['A', 'B']);

    $this->artisan('recommendations:rebuild')
        ->assertSuccessful();

    Queue::assertPushed(RecalculateRecommendationsJob::class, 2);
});

it('fails when no models are configured and none are given', function () {
    config()->set('recommendations.models', []);

    $this->artisan('recommendations:rebuild')
        ->assertExitCode(1);
});

it('honours the --tenant flag', function () {
    if (! $this->tenancyEnabled()) {
        $this->markTestSkipped('Only meaningful in multi-tenant mode.');
    }

    Queue::fake();

    createProductsSynthetic(['A', 'B']);

    $this->artisan('recommendations:rebuild', [
        'model' => Product::class,
        '--tenant' => 'tenant-1',
    ])->assertSuccessful();

    Queue::assertPushed(RecalculateRecommendationsJob::class, 2);
    Queue::assertPushed(RecalculateRecommendationsJob::class, 2, function (RecalculateRecommendationsJob $job) {
        return $job->tenantId === 'tenant-1';
    });
});

it('iterates all tenants when a TenantProvider is configured', function () {
    if (! $this->tenancyEnabled()) {
        $this->markTestSkipped('Only meaningful in multi-tenant mode.');
    }

    app()->instance(TenantProvider::class, new FakeTenantProvider(['tenant-1', 'tenant-2']));

    Queue::fake();

    createProductsSynthetic(['A', 'B']);

    $this->artisan('recommendations:rebuild', [
        'model' => Product::class,
    ])->assertSuccessful();

    // 2 records x 2 tenants = 4 jobs.
    Queue::assertPushed(RecalculateRecommendationsJob::class, 4);
});

it('rebuild leaves recommendations populated for the expected tenant', function () {
    if (! $this->tenancyEnabled()) {
        $this->markTestSkipped('Only meaningful in multi-tenant mode.');
    }

    config()->set('recommendations.events.recalculate_on_interaction', false);

    $this->resolver->setTenantId('tenant-1');
    [$a, $b] = createProductsSynthetic(['A', 'B']);
    $a->recordInteraction('viewed', $b);
    $b->recordInteraction('viewed', $a);

    $this->resolver->setTenantId('tenant-2');
    [$c, $d] = createProductsSynthetic(['C', 'D']);
    $c->recordInteraction('viewed', $d);
    $d->recordInteraction('viewed', $c);

    // Rebuild for tenant-1 only, running jobs synchronously.
    config()->set('queue.default', 'sync');

    $this->artisan('recommendations:rebuild', [
        'model' => Product::class,
        '--tenant' => 'tenant-1',
    ])->assertSuccessful();

    // Only tenant-1 rows exist.
    expect(Recommendation::query()->withoutGlobalScope('recommendation_tenant')->where('tenant_id', 'tenant-1')->count())->toBeGreaterThan(0);
    expect(Recommendation::query()->withoutGlobalScope('recommendation_tenant')->where('tenant_id', 'tenant-2')->count())->toBe(0);
});
