# Quick Start

Make a model recommendable in one line:

```php
use BoreiStudio\FilamentRecommendations\Traits\HasRecommendations;

class Product extends Model
{
    use HasRecommendations;
}
```

Show recommendations on a resource page in one line:

```php
use BoreiStudio\FilamentRecommendations\Resources\Concerns\HasRecommendationsWidget;

class ViewProduct extends ViewRecord
{
    use HasRecommendationsWidget;
}
```

Record an interaction — the recalculation job is dispatched automatically (default):

```php
$product->recordInteraction('viewed', $otherProduct);
```

Read recommendations:

```php
$product->recommend(); // Collection of recommended models
```

That's it. The full loop — interaction → recalculation → `recommend()` — works out of the box.
