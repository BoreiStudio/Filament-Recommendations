# Troubleshooting & FAQ

## The widget shows `recommendations::widgets.recommendations.empty`

That is a stale translation key from an older version. Re-publish the package assets and clear the view cache:

```bash
php artisan vendor:publish --tag=filament-recommendations-translations --force
php artisan view:clear
```

## `recommend()` returns nothing

1. Make sure a recalculation has run: `php artisan recommendations:rebuild`.
2. If `recalculate_on_interaction` is `false`, you must run the rebuild manually.
3. Confirm the model has the `HasRecommendations` trait.
4. In multi-tenant mode, confirm the `TenantResolver` is configured.

## Migration fails with "Specified key was too long"

The plugin ships MySQL-compatible indexes. If you published the migrations before v1.0, re-publish with `--force` and reset the tables.

## `TenantResolverNotConfigured`

Multi-tenant mode is enabled but `recommendations.tenancy.resolver` is not set. See [Multi-Tenant](06-multi-tenant/).

## The settings page is not visible

The page is registered by `RecommendationsPlugin::make()`. Make sure the plugin is registered on the panel, and that your user has access to the panel (implement `FilamentUser::canAccessPanel()`).

## How do I use a different strategy per model?

```php
'strategies' => [
    'models' => [
        Product::class => ContentBasedStrategy::class,
    ],
],
```

Or set it per tenant on the Recommendation Settings page.

## Does it work with Spatie Multitenancy / stancl?

Yes — implement the `TenantResolver` and `TenantProvider` contracts. See [Multi-Tenant](06-multi-tenant/).

## How do I change the number of results?

`results.limit` in the config, or per model/tenant on the settings page.

## How do I keep the events table from growing?

Run `php artisan recommendations:prune-events` on a schedule. See [Recalculation](04-recalculation/).
