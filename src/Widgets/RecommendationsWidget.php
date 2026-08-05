<?php

namespace BoreiStudio\FilamentRecommendations\Widgets;

use BoreiStudio\FilamentRecommendations\Support\RecommendationService;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class RecommendationsWidget extends Widget
{
    /**
     * @var view-string
     */
    protected string $view = 'recommendations::widgets.recommendations-widget';

    public ?Model $record = null;

    public static function canView(): bool
    {
        return true;
    }

    /**
     * @return Collection<int, Model>
     */
    public function getRecommendations(): Collection
    {
        if ($this->record === null) {
            return collect();
        }

        if (method_exists($this->record, 'recommend')) {
            return $this->record->recommend();
        }

        return app(RecommendationService::class)->for($this->record);
    }

    public function getRecommendationTitle(Model $recommendation): string
    {
        foreach (['name', 'title', 'label'] as $attribute) {
            if (filled($recommendation->{$attribute} ?? null)) {
                return (string) $recommendation->{$attribute};
            }
        }

        return class_basename($recommendation).' #'.$recommendation->getKey();
    }

    public function hasRecommendations(): bool
    {
        return $this->getRecommendations()->isNotEmpty();
    }
}
