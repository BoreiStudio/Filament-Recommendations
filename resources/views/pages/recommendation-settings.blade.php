<x-filament-panels::page>
    <x-filament::section
        :heading="__('filament-recommendations::messages.pages.recommendation-settings.title')"
        :description="$this->getTenantLabel()"
        icon="heroicon-o-cog-6-tooth"
    >
        <div class="mb-4 text-sm text-gray-500 dark:text-gray-400">
            {{ $this->getTenantLabel() }}
        </div>

        @if (empty(config('recommendations.models', [])))
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('filament-recommendations::messages.pages.recommendation-settings.no_models') }}
            </p>
        @else
            {{ $this->form }}
        @endif
    </x-filament::section>

    <x-filament::section
        :heading="__('filament-recommendations::messages.pages.recommendation-settings.rebuild_section')"
        :description="__('filament-recommendations::messages.pages.recommendation-settings.rebuild_helper')"
        icon="heroicon-o-arrow-path"
    >
        <p class="text-sm text-gray-700 dark:text-gray-300">
            {{ $this->getLastRebuildAt() ?? __('filament-recommendations::messages.pages.recommendation-settings.never') }}
        </p>
    </x-filament::section>
</x-filament-panels::page>
