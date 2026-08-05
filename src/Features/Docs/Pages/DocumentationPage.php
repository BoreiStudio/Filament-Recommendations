<?php

namespace BoreiStudio\FilamentRecommendations\Features\Docs\Pages;

use BoreiStudio\FilamentRecommendations\Clusters\RecommendationsConfigCluster;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;

class DocumentationPage extends Page
{
    protected static ?string $slug = 'docs';

    protected string $view = 'recommendations::docs.page';

    public static function getCluster(): ?string
    {
        return RecommendationsConfigCluster::class;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function getNavigationSort(): ?int
    {
        return 11;
    }

    public string $selectedModule = '02-quick-start';

    public string $content = '';

    /**
     * @var array<string, string>
     */
    public array $modules = [];

    public function mount(): void
    {
        $this->modules = $this->getModules();
        $this->loadContent();
    }

    public function selectModule(string $module): void
    {
        $this->selectedModule = $module;
        $this->loadContent();
    }

    public function loadContent(): void
    {
        $modules = $this->getModules();
        $path = __DIR__.'/../../../../docs/'.$this->selectedModule.'/index.md';

        if (! isset($modules[$this->selectedModule]) || ! file_exists($path)) {
            $this->content = '<p class="text-gray-400">Document not found.</p>';

            return;
        }

        $this->content = Str::markdown(file_get_contents($path), [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    public function copyMarkdown(): void
    {
        $path = __DIR__.'/../../../../docs/'.$this->selectedModule.'/index.md';

        if (! file_exists($path)) {
            return;
        }

        $this->js('navigator.clipboard.writeText('.json_encode(file_get_contents($path)).')');

        Notification::make()
            ->title('Markdown copied to clipboard.')
            ->success()
            ->send();
    }

    /**
     * @return array<string, string>
     */
    public function getModules(): array
    {
        return [
            '02-quick-start' => 'Quick Start',
            '01-installation' => 'Installation & Configuration',
            '03-strategies' => 'Strategies',
            '04-recalculation' => 'Recalculation',
            '05-filament-integration' => 'Filament Integration',
            '06-multi-tenant' => 'Multi-Tenant',
            '07-translations' => 'Translations',
            '08-testing' => 'Testing',
            '09-api-reference' => 'API Reference',
            '10-troubleshooting' => 'Troubleshooting & FAQ',
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return __('filament-recommendations::messages.pages.super-admin.documents');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-recommendations::messages.pages.super-admin.documents');
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-book-open';
    }
}
