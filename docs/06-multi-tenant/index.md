# Multi-Tenant

The plugin never assumes a tenancy package. It works through two contracts:

## TenantResolver

Tells the plugin which tenant is current:

```php
use BoreiStudio\FilamentRecommendations\Contracts\TenantResolver;

class YourTenantResolver implements TenantResolver
{
    public function resolveTenantId(): int|string|null
    {
        return Tenant::current()?->id;
    }
}
```

```php
// config/recommendations.php
'tenancy' => [
    'enabled' => true,
    'resolver' => YourTenantResolver::class,
],
```

## TenantProvider

Lets the rebuild command iterate all tenants:

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

```php
'tenancy' => [
    'tenant_provider' => YourTenantProvider::class,
],
```

## Spatie Multitenancy example

```php
use BoreiStudio\FilamentRecommendations\Contracts\TenantResolver;
use Spatie\Multitenancy\Models\Tenant;

class RecommendationsTenantResolver implements TenantResolver
{
    public function resolveTenantId(): int|string|null
    {
        return Tenant::current()?->getKey();
    }
}
```

The same pattern applies to `stancl/tenancy` (`tenancy()->tenant?->getKey()`).

## Isolation guarantees

Every query is scoped by `tenant_id` when tenancy is enabled. The package includes an isolation audit test suite that verifies no query can leak data between tenants.
