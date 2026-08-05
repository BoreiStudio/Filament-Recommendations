<?php

namespace BoreiStudio\FilamentRecommendations\Tests\Concerns;

use Filament\Actions\ActionsServiceProvider;
use Filament\Facades\Filament;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Panel;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Support\ViewErrorBag;
use Livewire\LivewireServiceProvider;

trait BootsFilamentPanel
{
    protected function bootFilamentPanel(): Panel
    {
        $this->app->config->set('livewire.class_namespace', 'Livewire\\Components');
        $this->app->config->set('livewire.view_path', resource_path('views/livewire'));
        $this->app->config->set('livewire.legacy_model_binding', false);
        $this->app->config->set('livewire.assert_session_has', false);

        foreach ([
            LivewireServiceProvider::class,
            SupportServiceProvider::class,
            SchemasServiceProvider::class,
            FormsServiceProvider::class,
            TablesServiceProvider::class,
            InfolistsServiceProvider::class,
            ActionsServiceProvider::class,
            NotificationsServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentServiceProvider::class,
        ] as $provider) {
            $this->app->register($provider);
        }

        view()->share('errors', new ViewErrorBag);

        $panel = Panel::make()
            ->id('admin')
            ->path('admin')
            ->default();

        Filament::registerPanel($panel);
        Filament::setCurrentPanel($panel);

        return $panel;
    }
}
