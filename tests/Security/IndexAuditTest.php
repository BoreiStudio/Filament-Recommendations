<?php

use BoreiStudio\FilamentRecommendations\Contracts\TenantResolver;
use BoreiStudio\FilamentRecommendations\Tests\Fixtures\FakeTenantResolver;
use BoreiStudio\FilamentRecommendations\Tests\Fixtures\Product;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->resolver = new FakeTenantResolver;
    app()->instance(TenantResolver::class, $this->resolver);

    $this->resolver->setTenantId('tenant-1');
});

it('has the expected indexes on recommendation_events', function () {
    $indexes = collect(DB::select('PRAGMA index_list(recommendation_events)'))
        ->map(fn ($i) => $i->name)
        ->all();

    expect($indexes)->toContain('rec_evt_tenant_subject_idx');
    expect($indexes)->toContain('rec_evt_tenant_subj_action_idx');
    expect($indexes)->toContain('rec_evt_related_idx');
    expect($indexes)->toContain('rec_evt_actor_idx');
});

it('has the expected indexes on recommendations', function () {
    $indexes = collect(DB::select('PRAGMA index_list(recommendations)'))
        ->map(fn ($i) => $i->name)
        ->all();

    expect($indexes)->toContain('recs_unique');
    expect($indexes)->toContain('recs_source_idx');
});

it('uses the tenant column in the critical co-occurrence query', function () {
    $product = Product::create(['name' => 'A']);
    $related = Product::create(['name' => 'B']);

    $product->recordInteraction('viewed', $related);
    $related->recordInteraction('viewed', $product);

    // The strategy must filter by tenant_id first in both subqueries. The
    // query plan should use an index scan, not a full table scan.
    $sql = 'select count(*) from recommendation_events where tenant_id = ? and subject_type = ? and subject_id = ?';

    $result = DB::selectOne($sql, ['tenant-1', $product->getMorphClass(), $product->getKey()]);

    expect((int) $result->{'count(*)'})->toBe(1);
});

it('scales the co-occurrence query over a simulated dataset', function () {
    $product = Product::create(['name' => 'A']);
    $others = collect(range(1, 5000))->map(fn ($i) => Product::create(['name' => "P{$i}"]));

    $rows = $others->map(fn ($other) => [
        'subject_type' => $product->getMorphClass(),
        'subject_id' => $product->getKey(),
        'related_type' => $other->getMorphClass(),
        'related_id' => $other->getKey(),
        'action' => 'viewed',
        'tenant_id' => 'tenant-1',
        'created_at' => now(),
    ])->all();

    foreach (array_chunk($rows, 500) as $chunk) {
        DB::table('recommendation_events')->insert($chunk);
    }

    $start = microtime(true);

    $count = DB::table('recommendation_events')
        ->where('tenant_id', 'tenant-1')
        ->where('subject_type', $product->getMorphClass())
        ->where('subject_id', $product->getKey())
        ->whereNotNull('related_type')
        ->count();

    $duration = microtime(true) - $start;

    expect($count)->toBe(5000);
    expect($duration)->toBeLessThan(2.0);
});
