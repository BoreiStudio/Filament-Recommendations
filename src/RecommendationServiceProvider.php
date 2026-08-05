<?php

namespace BoreiStudio\FilamentRecommendations;

use BoreiStudio\FilamentRecommendations\Contracts\FeatureProvider;
use BoreiStudio\FilamentRecommendations\Contracts\TenantResolver;
use BoreiStudio\FilamentRecommendations\Resolvers\DefaultFeatureProvider;
use BoreiStudio\FilamentRecommendations\Resolvers\DefaultTenantResolver;
use BoreiStudio\FilamentRecommendations\Support\FeatureResolver;
use BoreiStudio\FilamentRecommendations\Support\RecommendationService;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class RecommendationServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-recommendations')
            ->hasConfigFile('recommendations')
            ->hasMigration('create_recommendation_events_table')
            ->hasMigration('create_recommendations_table')
            ->hasMigration('create_recommendation_settings_table')
            ->hasViews('recommendations')
            ->hasTranslations()
            ->hasCommands([
                Console\InstallCommand::class,
                Console\RebuildRecommendationsCommand::class,
                Console\PruneEventsCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(RecommendationService::class);

        $this->app->singleton(FeatureResolver::class, function ($app) {
            $resolver = new FeatureResolver;

            $provider = config('recommendations.feature_provider');

            if (is_string($provider) && class_exists($provider)) {
                $resolver->addProvider($app->make($provider));
            }

            return $resolver;
        });

        $this->app->bind(FeatureProvider::class, DefaultFeatureProvider::class);

        $this->app->singleton(TenantResolver::class, function ($app) {
            $resolver = config('recommendations.tenancy.resolver');

            if (is_string($resolver) && class_exists($resolver)) {
                return $app->make($resolver);
            }

            return $app->make(DefaultTenantResolver::class);
        });
    }

    public function packageBooted(): void
    {
        FilamentAsset::register([
            Css::make('filament-recommendations', __DIR__.'/../resources/dist/filament-recommendations.css'),
        ], 'boreistudio/filament-recommendations');
    }
}
