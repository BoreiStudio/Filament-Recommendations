# API Reference

## Contracts

### `FeatureProvider`

Exposes content-based features for models that do not implement `Recommendable` (e.g. another plugin's model):

```php
interface FeatureProvider
{
    /** @return array<string, mixed> */
    public function features(Model $model): array;
    public function supports(Model $model): bool;
}
```

### `TenantResolver`

```php
interface TenantResolver
{
    public function resolveTenantId(): int|string|null;
}
```

### `TenantProvider`

```php
interface TenantProvider
{
    /** @return array<int, int|string> */
    public function allTenantIds(): array;
}
```

### `Recommendable`

```php
interface Recommendable
{
    public function getRecommendationFeatures(): array;
    public function getRecommendationKey(): string|int;
    public function getRecommendationTenantId(): int|string|null;
}
```

## Trait

### `HasRecommendations`

- `recommendations()` — relation to cached recommendations.
- `recommend(?int $limit = null): Collection` — ranked recommendations.
- `recordInteraction(string $action, ?Model $context = null, ?Model $actor = null): RecommendationEvent`

## Strategies

- `CoOccurrenceStrategy`
- `ContentBasedStrategy`
- `CollaborativeFilteringStrategy`

All implement `RecommendationStrategy::compute(Model $subject, int $limit = 10, int|string|null $tenantId = null): array`.

## Models

- `Recommendation` — cached results.
- `RecommendationEvent` — interaction log.
- `RecommendationSetting` — per-tenant/per-model configuration.

## Commands

- `recommendations:install`
- `recommendations:rebuild {model?} {--tenant=} {--strategy=} {--chunk=500}`
- `recommendations:prune-events {--tenant=} {--days=} {--chunk=500}`

## Jobs

- `RecalculateRecommendationsJob`

## Support classes

- `RecommendationCache` — `remember()`, `forget()`
- `RecommendationService` — `record($subject, $action, $context, $actor)`, `for($subject, $limit)` for models without the trait
- `FeatureResolver` — resolves content-based features via `Recommendable` or registered `FeatureProvider`s
- `RecommendationSettings` — per-tenant config helpers
- `StrategyResolver` — resolves the active strategy
- `RecommendationsGuard` — runtime precondition checks

## Exceptions

- `NotARecommendableModel`
- `TenantResolverNotConfigured`
- `MissingRecommendationsTable`
