<?php

use BoreiStudio\FilamentRecommendations\Features\Docs\Pages\DocumentationPage;
use BoreiStudio\FilamentRecommendations\Filament\RecommendationsPlugin;
use BoreiStudio\FilamentRecommendations\Pages\RecommendationSettingsPage;
use BoreiStudio\FilamentRecommendations\Pages\SuperAdminRecommendationsPage;
use BoreiStudio\FilamentRecommendations\Tests\Concerns\BootsFilamentPanel;
use BoreiStudio\FilamentRecommendations\Widgets\RecommendationsWidget;
use Filament\Facades\Filament;
use Filament\Panel;

uses(BootsFilamentPanel::class);

beforeEach(function () {
    $this->panel = $this->bootFilamentPanel();
});

it('registers the widget, settings page and documentation by default', function () {
    RecommendationsPlugin::make()->register($this->panel);

    expect($this->panel->getWidgets())->toContain(RecommendationsWidget::class);
    expect($this->panel->getPages())->toContain(RecommendationSettingsPage::class);
    expect($this->panel->getPages())->toContain(DocumentationPage::class);
    expect($this->panel->getPages())->not->toContain(SuperAdminRecommendationsPage::class);
});

it('can disable the documentation page', function () {
    RecommendationsPlugin::make()->withoutDocumentation()->register($this->panel);

    expect($this->panel->getPages())->not->toContain(DocumentationPage::class);
});

it('registers the super-admin page only when opted in', function () {
    RecommendationsPlugin::make()->withSuperAdminPage()->register($this->panel);

    expect($this->panel->getPages())->toContain(SuperAdminRecommendationsPage::class);
});

it('keeps the tenant panel isolated from the super-admin page', function () {
    $tenantPanel = Panel::make()->id('tenant')->path('tenant');
    Filament::registerPanel($tenantPanel);

    RecommendationsPlugin::make()->register($tenantPanel);

    expect($tenantPanel->getPages())->toContain(RecommendationSettingsPage::class);
    expect($tenantPanel->getPages())->not->toContain(SuperAdminRecommendationsPage::class);
});

it('keeps the super-admin panel isolated from the tenant settings page', function () {
    $superAdminPanel = Panel::make()->id('super-admin')->path('super-admin');
    Filament::registerPanel($superAdminPanel);

    RecommendationsPlugin::make()
        ->withoutSettingsPage()
        ->withSuperAdminPage()
        ->register($superAdminPanel);

    expect($superAdminPanel->getPages())->toContain(SuperAdminRecommendationsPage::class);
    expect($superAdminPanel->getPages())->not->toContain(RecommendationSettingsPage::class);
});

it('can disable the settings page and widget', function () {
    RecommendationsPlugin::make()
        ->withoutSettingsPage()
        ->withoutWidget()
        ->register($this->panel);

    expect($this->panel->getWidgets())->not->toContain(RecommendationsWidget::class);
    expect($this->panel->getPages())->not->toContain(RecommendationSettingsPage::class);
});

it('has a stable plugin id', function () {
    expect(RecommendationsPlugin::make()->getId())->toBe('filament-recommendations');
});
