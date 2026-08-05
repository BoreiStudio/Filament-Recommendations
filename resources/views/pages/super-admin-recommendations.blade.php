<x-filament-panels::page>
    <x-filament::section
        :heading="__('filament-recommendations::messages.pages.super-admin.health_section')"
        icon="heroicon-o-heart"
    >
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('filament-recommendations::messages.pages.super-admin.pending_jobs') }}
                </p>
                <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">
                    {{ $this->getPendingJobsCount() }}
                </p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('filament-recommendations::messages.pages.super-admin.failed_jobs') }}
                </p>
                <p class="mt-1 text-2xl font-semibold text-danger-600 dark:text-danger-400">
                    {{ $this->getFailedJobsCount() }}
                </p>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section
        :heading="__('filament-recommendations::messages.pages.super-admin.tenants_section')"
        icon="heroicon-o-building-office-2"
    >
        @if (empty($this->getTenantIds()))
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('filament-recommendations::messages.pages.super-admin.no_tenants') }}
            </p>
        @else
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach ($this->getTenantIds() as $tenantId)
                    @php($status = $this->getTenantStatus($tenantId))
                    <div class="flex items-center justify-between gap-4 py-3">
                        <div class="flex items-center gap-3">
                            <x-filament::avatar
                                class="h-9 w-9 rounded-full bg-gray-100 text-sm font-medium text-gray-500 dark:bg-gray-800 dark:text-gray-400"
                            >
                                {{ mb_substr((string) $tenantId, 0, 2) }}
                            </x-filament::avatar>
                            <div>
                                <p class="text-sm font-medium text-gray-950 dark:text-white">
                                    {{ $tenantId }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ __('filament-recommendations::messages.pages.super-admin.last_rebuild') }}:
                                    {{ $status['last_rebuild']
                                        ? \Illuminate\Support\Carbon::parse($status['last_rebuild'])->diffForHumans()
                                        : __('filament-recommendations::messages.pages.recommendation-settings.never') }}
                                </p>
                            </div>
                        </div>

                        <x-filament::button
                            size="sm"
                            color="gray"
                            icon="heroicon-o-arrow-path"
                            wire:click="recalculateForTenant('{{ $tenantId }}')"
                        >
                            {{ __('filament-recommendations::messages.pages.super-admin.recalculate') }}
                        </x-filament::button>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
