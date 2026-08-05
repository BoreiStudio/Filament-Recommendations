<?php

namespace BoreiStudio\FilamentRecommendations\Tests;

use BoreiStudio\FilamentRecommendations\RecommendationServiceProvider;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpConfig();
        $this->migratePackageTables();
    }

    protected function getPackageProviders($app): array
    {
        return [
            RecommendationServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');

        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUpConfig(): void
    {
        config()->set('recommendations.tenancy.enabled', $this->tenancyEnabled());
    }

    protected function tenancyEnabled(): bool
    {
        return env('RECOMMENDATIONS_TENANCY', 'single') === 'multi';
    }

    /**
     * Copies the package migrations (stored as .stub) and the fixture
     * migrations into a temporary folder and runs them on the test database.
     */
    protected function migratePackageTables(): void
    {
        $tempDir = sys_get_temp_dir().'/filament-recommendations-migrations-'.getmypid();

        File::ensureDirectoryExists($tempDir);

        foreach (glob(__DIR__.'/../database/migrations/*.php.stub') as $stub) {
            $name = str_replace('.php.stub', '', basename($stub));
            $destination = $tempDir.'/1000_00_00_000000_'.$name.'.php';

            if (! File::exists($destination)) {
                File::copy($stub, $destination);
            }
        }

        foreach (glob(__DIR__.'/Fixtures/database/migrations/*.php') as $migration) {
            $destination = $tempDir.'/'.basename($migration);

            if (! File::exists($destination)) {
                File::copy($migration, $destination);
            }
        }

        $this->artisan('migrate', [
            '--path' => $tempDir,
            '--realpath' => true,
        ])->run();
    }
}
