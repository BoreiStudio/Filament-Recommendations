# Recalculation

Recommendations are stored in the `recommendations` table and are never computed synchronously in a user request.

## Rebuild command

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

In multi-tenant mode the command iterates all tenants when a `TenantProvider` is configured under `tenancy.tenant_provider`.

## Scheduler

The rebuild and prune commands are good candidates for the scheduler:

```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('recommendations:rebuild')->daily()->withoutOverlapping();
    $schedule->command('recommendations:prune-events')->daily();
}
```

## Automatic recalculation on interaction

By default every `recordInteraction()` dispatches a recalculation job, so the loop works out of the box. To disable it:

```php
// config/recommendations.php
'events' => [
    'recalculate_on_interaction' => false,
],
```

## Caching

`recommend()` results are cached per subject and tenant. The cache is invalidated automatically when a recalculation job runs.

```php
// config/recommendations.php
'cache' => [
    'enabled' => true,
    'ttl' => 3600,
    'store' => env('RECOMMENDATIONS_CACHE_STORE'),
],
```

## Pruning interaction events

```bash
php artisan recommendations:prune-events              # uses events.retention_days
php artisan recommendations:prune-events --days=30
php artisan recommendations:prune-events --tenant=42
```
