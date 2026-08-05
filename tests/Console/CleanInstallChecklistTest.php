<?php

use BoreiStudio\FilamentRecommendations\Contracts\TenantResolver;
use BoreiStudio\FilamentRecommendations\Models\Recommendation;
use BoreiStudio\FilamentRecommendations\Tests\Fixtures\FakeTenantResolver;
use BoreiStudio\FilamentRecommendations\Tests\Fixtures\Product;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->resolver = new FakeTenantResolver;
    app()->instance(TenantResolver::class, $this->resolver);
});

/**
 * Follows the README installation flow: publish config + migrations, confirm
 * the published files exist (the tables are already migrated by the base
 * TestCase), then use the trait end-to-end.
 */
function runCleanInstall(object $test, array $flags = []): void
{
    File::ensureDirectoryExists(database_path('migrations'));

    foreach (glob(database_path('migrations/*recommendation*.php')) as $file) {
        File::delete($file);
    }

    $test->artisan('recommendations:install', [
        ...$flags,
        '--publish-migrations' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();
}

it('supports a clean single-tenant install from the README', function () {
    runCleanInstall($this);

    config()->set('recommendations.tenancy.enabled', false);

    expect(config_path('recommendations.php'))->toBeFile();
    expect(glob(database_path('migrations/*create_recommendation*.php')))->not->toBeEmpty();

    // The README quickstart: record an interaction and get recommendations.
    $product = Product::create(['name' => 'A']);
    $related = Product::create(['name' => 'B']);

    $product->recordInteraction('viewed', $related);

    // With the default recalculate_on_interaction=true the recommendation is
    // computed for the subject automatically (sync queue in tests).
    expect($product->recommend()->pluck('name'))->toContain('B');
});

it('supports a clean multi-tenant install with super-admin from the README', function () {
    if (! $this->tenancyEnabled()) {
        $this->markTestSkipped('Only meaningful in multi-tenant mode.');
    }

    runCleanInstall($this, [
        '--multi-tenant' => true,
        '--tenant-model' => 'App\\Models\\Tenant',
        '--generate-tenant-model' => true,
        '--super-admin' => true,
    ]);

    expect(config_path('recommendations.php'))->toBeFile();
    expect(app_path('Providers/Filament/TenantPanelProvider.php'))->toBeFile();
    expect(app_path('Providers/Filament/SuperAdminPanelProvider.php'))->toBeFile();

    $published = include config_path('recommendations.php');
    expect($published['tenancy']['enabled'])->toBeTrue();

    $this->resolver->setTenantId('tenant-1');
    $product = Product::create(['name' => 'A']);
    $related = Product::create(['name' => 'B']);

    $product->recordInteraction('viewed', $related);
    $related->recordInteraction('viewed', $product);

    expect($product->recommend()->pluck('name'))->toContain('B');
});
