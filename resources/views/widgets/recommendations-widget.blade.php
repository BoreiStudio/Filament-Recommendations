<x-filament-widgets::widget class="fi-recommendations-widget">
    <x-filament::section
        :heading="__('filament-recommendations::messages.widgets.recommendations.heading')"
        icon="heroicon-o-light-bulb"
    >
        @if ($this->hasRecommendations())
            <div class="fi-recommendations-widget-list grid gap-3">
                @foreach ($this->getRecommendations() as $recommendation)
                    <div class="fi-recommendations-widget-item">
                        <div class="flex items-center gap-3">
                            <div
                                class="fi-avatar flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 text-sm font-medium text-gray-500 dark:bg-gray-800 dark:text-gray-400"
                            >
                                {{ mb_substr($this->getRecommendationTitle($recommendation), 0, 2) }}
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-950 dark:text-white">
                                    {{ $this->getRecommendationTitle($recommendation) }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                <x-heroicon-o-sparkles class="h-5 w-5" />
                {{ __('filament-recommendations::messages.widgets.recommendations.empty') }}
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
