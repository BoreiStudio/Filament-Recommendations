<?php

use BoreiStudio\FilamentRecommendations\Strategies\CollaborativeFilteringStrategy;
use BoreiStudio\FilamentRecommendations\Strategies\ContentBasedStrategy;
use BoreiStudio\FilamentRecommendations\Strategies\CoOccurrenceStrategy;

return [

    /*
    |--------------------------------------------------------------------------
    | Tenancy
    |--------------------------------------------------------------------------
    |
    | The plugin never assumes a specific tenancy package. It exposes a
    | TenantResolver contract that the host project implements to tell the
    | plugin which tenant is current (or null in single-tenant mode).
    |
    | All plugin tables carry a nullable `tenant_id` column. In single-tenant
    | mode it stays null and is ignored; in multi-tenant mode it is used to
    | scope every query so data never leaks between tenants.
    |
    */

    'tenancy' => [
        'enabled' => false, // recommendations.tenancy.enabled

        /*
         * Class implementing BoreiStudio\FilamentRecommendations\Contracts\TenantResolver.
         * In single-tenant mode it may simply return null.
         */
        'resolver' => null, // recommendations.tenancy.resolver

        /*
         * Optional class implementing
         * BoreiStudio\FilamentRecommendations\Contracts\TenantProvider. Used by
         * `recommendations:rebuild` to iterate all tenants in multi-tenant mode.
         */
        'tenant_provider' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Strategies
    |--------------------------------------------------------------------------
    |
    | The default strategy is used by the recalculate job and by the
    | `recommendations:rebuild` command when no strategy is specified.
    |
    */

    'strategies' => [
        'default' => CoOccurrenceStrategy::class,

        'registry' => [
            CoOccurrenceStrategy::class,
            ContentBasedStrategy::class,
            CollaborativeFilteringStrategy::class,
        ],

        /*
         * Optional per-model strategy overrides, keyed by the model class:
         * 'models' => [Product::class => ContentBasedStrategy::class]
         */
        'models' => [],

        /*
         * Optional attribute weights for ContentBasedStrategy, keyed by the
         * model class. Attributes without an explicit weight use 1.
         * 'weights' => [Product::class => ['category' => 2, 'price' => 0.5]]
         */
        'weights' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature provider
    |--------------------------------------------------------------------------
    |
    | Optional class implementing FeatureProvider that exposes content-based
    | features for models that do not implement Recommendable (e.g. a Product
    | model owned by another plugin such as filament-store).
    |
    */

    'feature_provider' => null,

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Recommendations are cached per subject so the expensive calculation is
    | not repeated on every request. TTL is expressed in seconds.
    |
    */

    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
        'store' => env('RECOMMENDATIONS_CACHE_STORE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Results
    |--------------------------------------------------------------------------
    |
    | Default maximum number of recommendations returned by the recommend()
    | helper and the Filament widget.
    |
    */

    'results' => [
        'limit' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Recommendable models
    |--------------------------------------------------------------------------
    |
    | List of model classes that use the HasRecommendations trait. Used by the
    | `recommendations:rebuild` command when no model argument is given.
    |
    */

    'models' => [],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    |
    | The recommendation_events table powers the co-occurrence strategy. You
    | may disable event recording entirely (e.g. content-based only setups).
    |
    */

    'events' => [
        'enabled' => true,
        'retention_days' => 90,

        /*
         * When true, every recordInteraction() dispatches a
         * RecalculateRecommendationsJob for the subject (and context). Set to
         * false to recalculate only via `recommendations:rebuild`.
         */
        'recalculate_on_interaction' => true,
    ],
];
