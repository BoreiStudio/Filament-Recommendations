<?php

namespace BoreiStudio\FilamentRecommendations\Resources\Concerns;

use BoreiStudio\FilamentRecommendations\Widgets\RecommendationsWidget;

/**
 * Adds a `RecommendationsWidget` to the header of a resource page (View/Edit).
 * Use on the resource page class:
 *
 *     class ViewProduct extends ViewRecord
 *     {
 *         use HasRecommendationsWidget;
 *     }
 */
trait HasRecommendationsWidget
{
    /**
     * @return array<class-string>
     */
    public function getHeaderWidgets(): array
    {
        return [
            ...parent::getHeaderWidgets(),
            RecommendationsWidget::class,
        ];
    }
}
