# Filament Recommendations

Recommendation engine plugin for **Filament v5**. Co-occurrence, content-based and collaborative filtering strategies with **first-class multi-tenancy** support.

- **Single-tenant and multi-tenant ready.** The plugin never assumes a specific tenancy package. It exposes a `TenantResolver` contract that your project implements.
- **Multi-panel by design.** A separate Super-Admin panel (global config, health, cross-tenant debug) can be generated apart from the per-tenant panel.
- **Background recomputation.** Never compute recommendations synchronously in a user request.

## Requirements

- PHP 8.3+
- Laravel 12 or 13
- Filament v5

## Quickstart

Make a model recommendable, and show recommendations in a resource page in five lines:

```php
use BoreiStudio\FilamentRecommendations\Traits\HasRecommendations;

class Product extends Model
{
    use HasRecommendations;
}
```

```php
use BoreiStudio\FilamentRecommendations\Resources\Concerns\HasRecommendationsWidget;

class ViewProduct extends ViewRecord
{
    use HasRecommendationsWidget; // shows the RecommendationsWidget on the View page
}
```

```php
$product->recommend();                 // Collection of recommended models
$product->recordInteraction('viewed', $otherProduct);
```

## Installation

```bash
composer require boreistudio/filament-recommendations
```

Then run the interactive installer:

```bash
php artisan recommendations:install
```

The installer asks you about your topology (single vs multi-tenant), your Tenant model, whether to generate a separate Super-Admin panel, and which publishables to copy. It writes `config/recommendations.php` and optionally generates the panel providers.

For CI/non-interactive installs, flags are available:

```bash
# Single-tenant, publish migrations immediately
php artisan recommendations:install --single-tenant --publish-migrations

# Multi-tenant with an existing model and Super-Admin panel
php artisan recommendations:install \
    --multi-tenant \
    --tenant-model="App\Models\Tenant" \
    --super-admin \
    --publish-migrations

# Multi-tenant, generate a basic Tenant model
php artisan recommendations:install \
    --multi-tenant \
    --tenant-model="App\Models\Tenant" \
    --generate-tenant-model \
    --super-admin
```

The command is idempotent: re-running it will not overwrite existing files unless you pass `--force`.

After publishing the migrations, register them in the package's tables:

```bash
php artisan migrate
```

> When you generate the panels during installation, register the generated
> `App\Providers\Filament\TenantPanelProvider` (and `SuperAdminPanelProvider`, if chosen)
> in `bootstrap/providers.php`.

## Configuration

`config/recommendations.php`:

| Key | Default | Description |
| --- | --- | --- |
| `tenancy.enabled` | `false` | Enable tenant scoping on every query. |
| `tenancy.resolver` | `null` | Class implementing `BoreiStudio\FilamentRecommendations\Contracts\TenantResolver`. |
| `tenancy.tenant_provider` | `null` | Class implementing `BoreiStudio\FilamentRecommendations\Contracts\TenantProvider` (used by the rebuild command to iterate tenants). |
| `strategies.default` | `CoOccurrenceStrategy::class` | Strategy used by jobs and the rebuild command. |
| `strategies.registry` | `[]` | All available strategies. |
| `strategies.models` | `[]` | Per-model strategy overrides (`Product::class => ContentBasedStrategy::class`). |
| `strategies.weights` | `[]` | Per-model attribute weights for `ContentBasedStrategy`. |
| `cache.enabled` | `true` | Cache the `recommend()` results for a subject (see [Caching](#caching)). |
| `cache.ttl` | `3600` | Cache TTL in seconds. |
| `results.limit` | `10` | Default number of recommendations. |
| `models` | `[]` | Recommendable model classes used by `recommendations:rebuild` when no argument is given. |
| `events.enabled` | `true` | Record interaction events. |
| `events.retention_days` | `90` | Event retention policy used by `recommendations:prune-events`. |
| `events.recalculate_on_interaction` | `true` | Dispatch a recalculation job on every `recordInteraction()`. |

## Tenancy

The plugin never assumes a tenancy package. Implement `TenantResolver` and bind it in `config/recommendations.php` under `tenancy.resolver`:

```php
use BoreiStudio\FilamentRecommendations\Contracts\TenantResolver;

class YourTenantResolver implements TenantResolver
{
    public function resolveTenantId(): int|string|null
    {
        return Tenant::current()?->id; // whatever your tenancy setup uses
    }
}
```

In single-tenant mode you can leave the resolver unset (the plugin falls back to a resolver that always returns `null`).

Models with the trait also implement `Recommendable` and get sensible defaults:
- `getRecommendationFeatures(): array` — empty by default, used by content-based strategies
- `getRecommendationKey()` — the model primary key
- `getRecommendationTenantId()` — delegates to the `TenantResolver`, override to pin a tenant

When `tenancy.enabled` is `true`, the `recommendations` and `recommendation_events` tables are scoped to the resolved tenant automatically, so a tenant can never read another tenant's data.

## Strategies

Strategies implement `BoreiStudio\FilamentRecommendations\Strategies\RecommendationStrategy` and return a ranked list of `[recommended_type, recommended_id, score]` for a subject model, always scoped to the resolved tenant.

### Co-occurrence ("who also viewed")

`CoOccurrenceStrategy` counts how many times two items appear together in the same interaction, using an aggregated SQL query (`GROUP BY + COUNT`), never loading the dataset into PHP. It is the default strategy.

```php
use BoreiStudio\FilamentRecommendations\Strategies\CoOccurrenceStrategy;

$strategy = new CoOccurrenceStrategy;
$results = $strategy->compute($product, 10); // [[recommended_type, recommended_id, score], ...]
```

### Content-based

`ContentBasedStrategy` recommends items with similar normalized attributes, based on `getRecommendationFeatures()`. Categorical / multi-value attributes use Jaccard similarity; numeric attributes are min-max scaled across the candidate set. Attribute weights are configurable per model:

```php
// config/recommendations.php
'strategies' => [
    'weights' => [
        Product::class => ['category' => 2, 'price' => 0.5],
    ],
],
```

```php
class Product extends Model implements Recommendable
{
    use HasRecommendations;

    public function getRecommendationFeatures(): array
    {
        return [
            'category' => $this->category,
            'tags' => $this->tags,      // arrays are compared with Jaccard
            'price' => (float) $this->price,
        ];
    }
}
```

Two models with identical features recommend each other with score `1.0`; completely disjoint features score `~0`.

### Collaborative filtering (advanced / optional)

`CollaborativeFilteringStrategy` is an item-based approach ("users who interacted with this item also interacted with these others") built on a user-item matrix from `recommendation_events`, ranked by cosine similarity over the shared user space.

> **This strategy is more expensive** and only becomes useful with a meaningful volume of per-user interactions. It is meant for advanced setups — prefer `CoOccurrenceStrategy` by default.

Record interactions with an actor (user) so the matrix can be built:

```php
$product->recordInteraction('viewed', $otherProduct, $user); // subject, context, actor
```

Activate it per model:

```php
// config/recommendations.php
'strategies' => [
    'models' => [
        Product::class => \BoreiStudio\FilamentRecommendations\Strategies\CollaborativeFilteringStrategy::class,
    ],
],
```

The optional `rubix/ml` dependency (`composer suggest`) can power a more sophisticated implementation if you need it.

## Filament integration

### Widget

Add the `RecommendationsWidget` to the header of a resource's View/Edit page in one line:

```php
use BoreiStudio\FilamentRecommendations\Resources\Concerns\HasRecommendationsWidget;

class ViewProduct extends ViewRecord
{
    use HasRecommendationsWidget;
}
```

Or register it anywhere on a panel:

```php
// App\Providers\Filament\TenantPanelProvider.php
->plugins([
    \BoreiStudio\FilamentRecommendations\Filament\RecommendationsPlugin::make(),
])
```

The widget shows the cached recommendations for the current record, scoped to the tenant automatically.

### Configuration cluster

The settings page and the in-panel documentation are grouped under a single **Recommendations** cluster (`/admin/recommendations-config`):

- `settings` — per-tenant strategy + result limit per model, and last rebuild time
- `docs` — in-panel documentation (toggle with `->documentation(true|false)`)

### Super-admin panel

If you generated the `SuperAdminPanelProvider` during `recommendations:install`, register the plugin with the super-admin page:

```php
// App\Providers\Filament\SuperAdminPanelProvider.php
->plugins([
    \BoreiStudio\FilamentRecommendations\Filament\RecommendationsPlugin::make()
        ->withoutSettingsPage()
        ->withSuperAdminPage(),
])
```

The `SuperAdminRecommendationsPage` shows pending/failed jobs, the last rebuild per tenant (via the `TenantProvider`), and lets you trigger recalculation per tenant or globally.

> **Guard integration.** The plugin does not assume a super-admin user model. Restrict access to the super-admin panel with your existing auth: implement `FilamentUser::canAccessPanel()` on your user model to return `true` only for the `super-admin` panel id (or gate it via a role/permission).

## Recalculation

Recommendations are stored in the `recommendations` table and are never computed synchronously in a user request.

### Rebuild command

```bash
# Rebuild all recommendable models configured in recommendations.models
php artisan recommendations:rebuild

# Rebuild a single model
php artisan recommendations:rebuild "App\Models\Product"

# Only for one tenant
php artisan recommendations:rebuild "App\Models\Product" --tenant=42

# With a specific strategy and chunk size
php artisan recommendations:rebuild "App\Models\Product" --strategy="BoreiStudio\FilamentRecommendations\Strategies\CoOccurrenceStrategy" --chunk=250
```

In multi-tenant mode the command iterates all tenants when a `TenantProvider` is configured in `config/recommendations.php` under `tenancy.tenant_provider`:

```php
use BoreiStudio\FilamentRecommendations\Contracts\TenantProvider;

class YourTenantProvider implements TenantProvider
{
    public function allTenantIds(): array
    {
        return Tenant::query()->pluck('id')->all();
    }
}
```

### Scheduler

The rebuild and prune commands are good candidates for the scheduler:

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('recommendations:rebuild')->daily()->withoutOverlapping();
    $schedule->command('recommendations:prune-events')->daily();
}
```

### Automatic recalculation on interaction

By default every `recordInteraction()` dispatches a recalculation job for the subject (and context), so the loop works out of the box:

```php
$product->recordInteraction('viewed', $otherProduct); // queues a recalc job

$product->recommend(); // recommendations are already up to date
```

To recalculate only via `recommendations:rebuild`, disable it:

```php
// config/recommendations.php
'events' => [
    'recalculate_on_interaction' => false,
],
```

### Caching

`recommend()` results are cached per subject and tenant, so the expensive query never runs on every request. The cache is invalidated automatically when a recalculation job runs for that subject.

```php
// config/recommendations.php
'cache' => [
    'enabled' => true,
    'ttl' => 3600,
    'store' => env('RECOMMENDATIONS_CACHE_STORE'), // null = default cache store
],
```

If you change recommendations outside the plugin (e.g. editing the `recommendations` table directly), clear the cache with `php artisan cache:clear` or invalidate per subject:

```php
use BoreiStudio\FilamentRecommendations\Support\RecommendationCache;

RecommendationCache::forget($product, $product->getRecommendationTenantId());
```

### Pruning interaction events

Events power the co-occurrence strategy but grow without bound. Prune old ones with the retention policy (`events.retention_days`), optionally per tenant:

```bash
php artisan recommendations:prune-events              # uses events.retention_days
php artisan recommendations:prune-events --days=30
php artisan recommendations:prune-events --tenant=42
```

## Production hardening

### Error handling

Clear exceptions are thrown for common misconfigurations:

| Situation | Exception |
| --- | --- |
| Model without the `HasRecommendations` trait passed to a strategy | `NotARecommendableModel` |
| Unknown strategy class in `strategies.*` | `InvalidArgumentException` |
| Multi-tenant enabled without a `TenantResolver` | `TenantResolverNotConfigured` |
| Package tables not migrated | `MissingRecommendationsTable` |

### Integrating with a tenancy package

The plugin never assumes a tenancy package — it works through the `TenantResolver` / `TenantProvider` contracts. Example with **spatie/laravel-multitenancy**:

```php
// app/Support/RecommendationsTenantResolver.php
use BoreiStudio\FilamentRecommendations\Contracts\TenantResolver;
use Spatie\Multitenancy\Models\Tenant;

class RecommendationsTenantResolver implements TenantResolver
{
    public function resolveTenantId(): int|string|null
    {
        return Tenant::current()?->getKey();
    }
}

// app/Support/RecommendationsTenantProvider.php
use BoreiStudio\FilamentRecommendations\Contracts\TenantProvider;

class RecommendationsTenantProvider implements TenantProvider
{
    public function allTenantIds(): array
    {
        return Tenant::query()->pluck('id')->all();
    }
}
```

```php
// config/recommendations.php
'tenancy' => [
    'enabled' => true,
    'resolver' => RecommendationsTenantResolver::class,
    'tenant_provider' => RecommendationsTenantProvider::class,
],
```

The same pattern applies to `stancl/tenancy` (use `tenancy()->tenant?->getKey()` / `Tenant::query()->pluck('id')`).

### Benchmarks

Co-occurrence computation is fully aggregated in SQL (never loaded into PHP). On SQLite via testbench:

- **~10k events** across 5 tenants × 20 users × 100 products: co-occurrence compute resolves in **~0.2s** (20 subjects).
- A tenant-scoped count over **5,000 events** for one subject: **~0.4s** including row inserts.

## Development

Clone the repository and install dependencies:

```bash
composer install
```

Run the test suite in both tenancy modes:

```bash
composer test                                # single-tenant
RECOMMENDATIONS_TENANCY=multi composer test  # multi-tenant
```

Run the code style and static analysis:

```bash
composer format    # Laravel Pint
composer analyse   # PHPStan
```

The test matrix (PHP 8.3/8.4 × Laravel 12/13 × single/multi-tenant) runs on GitHub Actions for every push.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
