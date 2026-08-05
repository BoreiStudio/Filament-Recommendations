<?php

namespace BoreiStudio\FilamentRecommendations\Filament;

use BoreiStudio\FilamentRecommendations\Features\Docs\Pages\DocumentationPage;
use BoreiStudio\FilamentRecommendations\Pages\RecommendationSettingsPage;
use BoreiStudio\FilamentRecommendations\Pages\SuperAdminRecommendationsPage;
use BoreiStudio\FilamentRecommendations\Widgets\RecommendationsWidget;
use Filament\Contracts\Plugin as FilamentPlugin;
use Filament\Panel;

/**
 * Registers the plugin's Filament pieces (widget + settings page) on a panel.
 *
 *     ->plugins([
 *         \BoreiStudio\FilamentRecommendations\Filament\RecommendationsPlugin::make(),
 *     ])
 */
class RecommendationsPlugin implements FilamentPlugin
{
    protected bool $hasSettingsPage = true;

    protected bool $hasWidget = true;

    protected bool $hasSuperAdminPage = false;

    protected bool $hasDocumentation = true;

    public static function make(): static
    {
        return app(static::class);
    }

    public function withoutSettingsPage(): static
    {
        $this->hasSettingsPage = false;

        return $this;
    }

    public function withoutWidget(): static
    {
        $this->hasWidget = false;

        return $this;
    }

    public function withSuperAdminPage(): static
    {
        $this->hasSuperAdminPage = true;

        return $this;
    }

    public function documentation(bool $condition = true): static
    {
        $this->hasDocumentation = $condition;

        return $this;
    }

    public function withoutDocumentation(): static
    {
        $this->hasDocumentation = false;

        return $this;
    }

    public function getId(): string
    {
        return 'filament-recommendations';
    }

    public function register(Panel $panel): void
    {
        if ($this->hasWidget) {
            $panel->widgets([
                RecommendationsWidget::class,
            ]);
        }

        $panel->discoverClusters(
            in: __DIR__.'/../Clusters',
            for: 'BoreiStudio\\FilamentRecommendations\\Clusters',
        );

        $pages = [];

        if ($this->hasSettingsPage) {
            $pages[] = RecommendationSettingsPage::class;
        }

        if ($this->hasSuperAdminPage) {
            $pages[] = SuperAdminRecommendationsPage::class;
        }

        if ($this->hasDocumentation) {
            $pages[] = DocumentationPage::class;
        }

        if ($pages !== []) {
            $panel->pages($pages);
        }
    }

    public function boot(Panel $panel): void {}
}
