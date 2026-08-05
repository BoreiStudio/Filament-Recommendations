<?php

namespace BoreiStudio\FilamentRecommendations\Pages;

use BackedEnum;
use BoreiStudio\FilamentRecommendations\Contracts\TenantProvider;
use BoreiStudio\FilamentRecommendations\Jobs\RecalculateRecommendationsJob;
use BoreiStudio\FilamentRecommendations\Models\Recommendation;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * Super-admin health page: shows tenant strategy configuration, job health and
 * last rebuild per tenant, plus "recalculate now" actions.
 */
class SuperAdminRecommendationsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected string $view = 'recommendations::pages.super-admin-recommendations';

    public function recalculateFor(?string $tenantId): void
    {
        foreach ((array) config('recommendations.models', []) as $model) {
            $model::query()
                ->chunkById(500, function ($records) use ($tenantId) {
                    foreach ($records as $record) {
                        RecalculateRecommendationsJob::dispatch($record, strategy: null, tenantId: $tenantId);
                    }
                });
        }

        Notification::make()
            ->title(__('filament-recommendations::messages.pages.super-admin.recalculate_queued'))
            ->success()
            ->send();
    }

    public function recalculateForTenant(string $tenantId): void
    {
        $this->recalculateFor($tenantId);
    }

    /**
     * @return array<int, int|string>
     */
    public function getTenantIds(): array
    {
        $provider = config('recommendations.tenancy.tenant_provider');

        if (is_string($provider) && is_subclass_of($provider, TenantProvider::class)) {
            return app($provider)->allTenantIds();
        }

        return [];
    }

    /**
     * @return array{last_rebuild: string|null}
     */
    public function getTenantStatus(string $tenantId): array
    {
        return [
            'last_rebuild' => Recommendation::query()
                ->withoutGlobalScope('recommendation_tenant')
                ->where('tenant_id', $tenantId)
                ->max('computed_at'),
        ];
    }

    public function getFailedJobsCount(): int
    {
        try {
            return DB::table(config('queue.failed.table', 'failed_jobs'))->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    public function getPendingJobsCount(): int
    {
        try {
            return Queue::size();
        } catch (\Throwable) {
            return 0;
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recalculate-all')
                ->label(__('filament-recommendations::messages.pages.super-admin.recalculate_all'))
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => $this->recalculateFor(null)),
        ];
    }
}
