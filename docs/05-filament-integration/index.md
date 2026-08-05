# Filament Integration

## Configuration cluster

The settings page and the in-panel documentation are grouped under a single **Recommendations** config cluster:

- `admin/recommendations-config/settings`
- `admin/recommendations-config/docs`

## Widget

Add the `RecommendationsWidget` to the header of a resource's View/Edit page in one line:

```php
use BoreiStudio\FilamentRecommendations\Resources\Concerns\HasRecommendationsWidget;

class ViewProduct extends ViewRecord
{
    use HasRecommendationsWidget;
}
```

Or register it on a panel:

```php
->plugin(\BoreiStudio\FilamentRecommendations\Filament\RecommendationsPlugin::make())
```

## Tenant settings page

`RecommendationSettingsPage` lives in the **Recommendations** cluster. It lets each tenant choose the active strategy and result limit per model, and shows the last rebuild time — always scoped to their own tenant.

## Super-admin panel

If you generated the `SuperAdminPanelProvider` during installation, register the plugin with the super-admin page:

```php
->plugin(\BoreiStudio\FilamentRecommendations\Filament\RecommendationsPlugin::make()
    ->withoutSettingsPage()
    ->withSuperAdminPage())
```

The `SuperAdminRecommendationsPage` shows pending/failed jobs, the last rebuild per tenant, and lets you trigger recalculation per tenant or globally.

> **Guard integration.** The plugin does not assume a super-admin user model. Restrict access to the super-admin panel with your existing auth: implement `FilamentUser::canAccessPanel()` on your user model to return `true` only for the `super-admin` panel id.

## Documentation page

The plugin ships with an in-panel documentation page in the **Recommendations** cluster. Toggle it on or off:

```php
->plugin(RecommendationsPlugin::make()->documentation(true))   // default
->plugin(RecommendationsPlugin::make()->documentation(false))  // hide
```

## Integrating with another plugin (e.g. filament-store)

For models you don't own (like the `Product` model of `boreistudio/filament-store`), use the `RecommendationService` instead of the trait — no model changes needed:

```php
use BoreiStudio\FilamentRecommendations\Support\RecommendationService;

$service = app(RecommendationService::class);

// Record an interaction for any Eloquent model
$service->record($product, 'viewed');

// Read recommendations for any Eloquent model
$recommendations = $service->for($product, 4);
```

The `RecommendationsWidget` also falls back to the service, so it renders recommendations for models without the trait.

`filament-store` ships this integration out of the box: enable it with `StorePlugin::make()->recommendations(true)` and the storefront records `viewed` / `added_to_cart` / `purchased` interactions automatically and shows a "Recommended For You" section on the product page.
