<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::deleteDirectory(app_path('Models'));
    File::deleteDirectory(app_path('Providers/Filament'));
    File::delete(config_path('recommendations.php'));
});

it('defaults to single-tenant when run non-interactively', function () {
    $this->artisan('recommendations:install', ['--no-interaction' => true])
        ->expectsOutputToContain('defaulting to single-tenant')
        ->assertSuccessful();

    $published = include config_path('recommendations.php');

    expect($published['tenancy']['enabled'])->toBeFalse();
    expect(app_path('Models/Tenant.php'))->not->toBeFile();
    expect(app_path('Providers/Filament/TenantPanelProvider.php'))->not->toBeFile();
});

it('installs in single-tenant mode', function () {
    $this->artisan('recommendations:install', ['--single-tenant' => true, '--no-interaction' => true])
        ->assertSuccessful();

    $published = include config_path('recommendations.php');

    expect($published['tenancy']['enabled'])->toBeFalse();
    expect($published['tenancy']['resolver'])->toBeNull();
    expect(app_path('Providers/Filament/TenantPanelProvider.php'))->not->toBeFile();
});

it('installs in multi-tenant mode and generates the tenant model', function () {
    $this->artisan('recommendations:install', [
        '--multi-tenant' => true,
        '--tenant-model' => 'App\\Models\\Tenant',
        '--generate-tenant-model' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    $published = include config_path('recommendations.php');

    expect($published['tenancy']['enabled'])->toBeTrue();
    expect(app_path('Models/Tenant.php'))->toBeFile();
    expect(app_path('Providers/Filament/TenantPanelProvider.php'))->not->toBeFile();

    require_once app_path('Models/Tenant.php');
    expect(class_exists('App\\Models\\Tenant'))->toBeTrue();
});

it('does not overwrite an existing tenant model without --force', function () {
    File::ensureDirectoryExists(app_path('Models'));
    File::put(app_path('Models/Tenant.php'), '<?php // my custom tenant');

    $this->artisan('recommendations:install', [
        '--multi-tenant' => true,
        '--tenant-model' => 'App\\Models\\Tenant',
        '--generate-tenant-model' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect(File::get(app_path('Models/Tenant.php')))->toBe('<?php // my custom tenant');
});

it('generates both panels with --super-admin', function () {
    $this->artisan('recommendations:install', [
        '--multi-tenant' => true,
        '--super-admin' => true,
        '--tenant-model' => 'App\\Models\\Tenant',
        '--generate-tenant-model' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect(app_path('Providers/Filament/TenantPanelProvider.php'))->toBeFile();
    expect(app_path('Providers/Filament/SuperAdminPanelProvider.php'))->toBeFile();
});

it('is idempotent when run twice', function () {
    $this->artisan('recommendations:install', [
        '--multi-tenant' => true,
        '--super-admin' => true,
        '--tenant-model' => 'App\\Models\\Tenant',
        '--generate-tenant-model' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    $first = File::get(app_path('Providers/Filament/TenantPanelProvider.php'));

    $this->artisan('recommendations:install', [
        '--multi-tenant' => true,
        '--super-admin' => true,
        '--tenant-model' => 'App\\Models\\Tenant',
        '--generate-tenant-model' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect(File::get(app_path('Providers/Filament/TenantPanelProvider.php')))->toBe($first);
});

it('fails when both --single-tenant and --multi-tenant are given', function () {
    $this->artisan('recommendations:install', [
        '--single-tenant' => true,
        '--multi-tenant' => true,
        '--no-interaction' => true,
    ])->assertExitCode(1);
});

it('publishes migrations with --publish-migrations', function () {
    $this->artisan('recommendations:install', [
        '--single-tenant' => true,
        '--publish-migrations' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    $migrations = glob(database_path('migrations/*create_recommendation*.php'));

    expect($migrations)->not->toBeEmpty();
    expect(count($migrations))->toBe(3);
});
