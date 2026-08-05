<?php

use BoreiStudio\FilamentRecommendations\Contracts\TenantProvider;
use BoreiStudio\FilamentRecommendations\Contracts\TenantResolver;
use BoreiStudio\FilamentRecommendations\Jobs\RecalculateRecommendationsJob;
use BoreiStudio\FilamentRecommendations\Pages\SuperAdminRecommendationsPage;
use BoreiStudio\FilamentRecommendations\Tests\Fixtures\FakeTenantResolver;
use BoreiStudio\FilamentRecommendations\Tests\Fixtures\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

class SuperAdminTenantProvider implements TenantProvider
{
    public function allTenantIds(): array
    {
        return ['tenant-1', 'tenant-2'];
    }
}

beforeEach(function () {
    $this->resolver = new FakeTenantResolver;
    app()->instance(TenantResolver::class, $this->resolver);

    app()->instance(TenantProvider::class, new SuperAdminTenantProvider);
    config()->set('recommendations.tenancy.tenant_provider', SuperAdminTenantProvider::class);

    config()->set('recommendations.models', [Product::class]);
});

it('lists the active tenants from the TenantProvider', function () {
    $page = new SuperAdminRecommendationsPage;

    expect($page->getTenantIds())->toBe(['tenant-1', 'tenant-2']);
});

it('reports the last rebuild per tenant', function () {
    $product = Product::create(['name' => 'A']);
    $related = Product::create(['name' => 'B']);

    $this->resolver->setTenantId('tenant-1');
    $product->recommendations()->create([
        'recommended_type' => $related->getMorphClass(),
        'recommended_id' => $related->getKey(),
        'score' => 1.0,
        'strategy' => 'co-occurrence',
        'computed_at' => now()->subHour(),
    ]);

    $this->resolver->setTenantId('tenant-2');
    $other = Product::create(['name' => 'C']);
    $other->recommendations()->create([
        'recommended_type' => $related->getMorphClass(),
        'recommended_id' => $related->getKey(),
        'score' => 1.0,
        'strategy' => 'co-occurrence',
        'computed_at' => now()->subMinutes(5),
    ]);

    $page = new SuperAdminRecommendationsPage;

    expect($page->getTenantStatus('tenant-1')['last_rebuild'])->not->toBeNull();
    expect($page->getTenantStatus('tenant-2')['last_rebuild'])->not->toBeNull();

    $t1 = Carbon::parse($page->getTenantStatus('tenant-1')['last_rebuild']);
    $t2 = Carbon::parse($page->getTenantStatus('tenant-2')['last_rebuild']);

    expect($t1->lessThan($t2))->toBeTrue();
});

it('queues a recalculation job for a specific tenant', function () {
    Queue::fake();

    Product::create(['name' => 'A']);
    Product::create(['name' => 'B']);

    $page = new SuperAdminRecommendationsPage;
    $page->recalculateForTenant('tenant-1');

    Queue::assertPushed(RecalculateRecommendationsJob::class, 2);
    Queue::assertPushed(RecalculateRecommendationsJob::class, fn (RecalculateRecommendationsJob $job) => $job->tenantId === 'tenant-1');
});

it('reports failed jobs count without errors when the table is missing', function () {
    config()->set('queue.failed.table', 'nonexistent_failed_jobs');

    $page = new SuperAdminRecommendationsPage;

    expect($page->getFailedJobsCount())->toBe(0);
});
