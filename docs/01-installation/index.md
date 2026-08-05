# Installation & Configuration

## Install

```bash
composer require boreistudio/filament-recommendations
php artisan recommendations:install
php artisan migrate
```

The interactive installer asks about your topology (single vs multi-tenant), your Tenant model, whether to generate a Super-Admin panel, and which publishables to copy.

## Non-interactive install (CI)

```bash
# Single-tenant, publish migrations immediately
php artisan recommendations:install --single-tenant --publish-migrations

# Multi-tenant with an existing model and Super-Admin panel
php artisan recommendations:install \
    --multi-tenant \
    --tenant-model="App\Models\Tenant" \
    --super-admin \
    --publish-migrations
```

The command is idempotent: re-running it does not overwrite existing files unless you pass `--force`.

## Register the plugin

In your `PanelProvider`:

```php
use BoreiStudio\FilamentRecommendations\Filament\RecommendationsPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugin(RecommendationsPlugin::make());
}
```

## CSS Build

The plugin ships with pre-compiled CSS. If you modify the Tailwind styles:

```bash
cd vendor/boreistudio/filament-recommendations
npm install
npm run build
php artisan filament:assets
```

## Configuration

The main configuration file is `config/recommendations.php`. See the [README](../README.md) for the full key reference.
