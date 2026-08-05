# Strategies

Strategies implement `BoreiStudio\FilamentRecommendations\Strategies\RecommendationStrategy` and return a ranked list of `[recommended_type, recommended_id, score]` for a subject model, always scoped to the resolved tenant.

## Co-occurrence ("who also viewed")

Counts how many times two items appear together in the same interaction, using an aggregated SQL query (`GROUP BY + COUNT`), never loading the dataset into PHP. This is the default strategy.

```php
use BoreiStudio\FilamentRecommendations\Strategies\CoOccurrenceStrategy;

$strategy = new CoOccurrenceStrategy;
$results = $strategy->compute($product, 10);
```

## Content-based

Recommends items with similar normalized attributes based on `getRecommendationFeatures()`. Categorical attributes use Jaccard similarity; numeric attributes are min-max scaled across the candidate set. Attribute weights are configurable per model.

```php
class Product extends Model implements Recommendable
{
    use HasRecommendations;

    public function getRecommendationFeatures(): array
    {
        return [
            'category' => $this->category,
            'tags' => $this->tags,
            'price' => (float) $this->price,
        ];
    }
}
```

```php
// config/recommendations.php
'strategies' => [
    'weights' => [
        Product::class => ['category' => 2, 'price' => 0.5],
    ],
],
```

## Collaborative filtering (advanced / optional)

Item-based approach ("users who interacted with this item also interacted with these others") built on a user-item matrix, ranked by cosine similarity.

> **This strategy is more expensive** and only becomes useful with a meaningful volume of per-user interactions.

Record interactions with an actor (user) so the matrix can be built:

```php
$product->recordInteraction('viewed', $otherProduct, $user);
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

## Switching strategies

Choose the active strategy per model from the Filament panel at **Recommendations → Settings**, then run:

```bash
php artisan recommendations:rebuild
```

Each recommendable model gets its own strategy selector (default: Co-occurrence). The setting overrides the `strategies.default` config.

## Content-based for models you don't own

If a model (like `filament-store`'s `Product`) does not implement `Recommendable`, register a `FeatureProvider`:

```php
use BoreiStudio\FilamentRecommendations\Contracts\FeatureProvider;

class ProductFeatureProvider implements FeatureProvider
{
    public function features(Model $model): array
    {
        return [
            'category' => $model->category?->name,
            'price' => (float) $model->price,
        ];
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Product;
    }
}
```

```php
// config/recommendations.php
'feature_provider' => ProductFeatureProvider::class,
```

`filament-store` already registers its own provider, so content-based works out of the box with store products.
