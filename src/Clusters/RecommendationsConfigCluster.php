<?php

namespace BoreiStudio\FilamentRecommendations\Clusters;

use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;

class RecommendationsConfigCluster extends Cluster
{
    public static function getSubNavigationPosition(): SubNavigationPosition
    {
        return SubNavigationPosition::Top;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament-recommendations::messages.navigation.config_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-recommendations::messages.navigation.recommendations');
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-light-bulb';
    }
}
