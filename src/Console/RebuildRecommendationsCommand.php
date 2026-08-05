<?php

namespace BoreiStudio\FilamentRecommendations\Console;

use BoreiStudio\FilamentRecommendations\Contracts\TenantProvider;
use BoreiStudio\FilamentRecommendations\Contracts\TenantResolver;
use BoreiStudio\FilamentRecommendations\Jobs\RecalculateRecommendationsJob;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class RebuildRecommendationsCommand extends Command
{
    protected $signature = 'recommendations:rebuild
        {model? : The Recommendable model class to rebuild (defaults to config recommendations.models)}
        {--tenant= : Only rebuild for this tenant id}
        {--strategy= : Strategy class to use (defaults to the configured default)}
        {--chunk=500 : Number of records per query chunk}';

    protected $description = 'Dispatch recalculation jobs for recommendable models, optionally scoped by tenant';

    public function handle(): int
    {
        $models = $this->resolveModels();

        if ($models === []) {
            $this->error('No recommendable models found. Pass a model class or configure recommendations.models.');

            return self::FAILURE;
        }

        $tenantIds = $this->resolveTenantIds();

        $chunk = (int) $this->option('chunk');
        $strategy = $this->option('strategy');

        foreach ($models as $modelClass) {
            $this->rebuildModel($modelClass, $tenantIds, $chunk, $strategy);
        }

        $this->newLine();
        $this->components->info('Recommendations rebuild dispatched.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    protected function resolveModels(): array
    {
        $argument = $this->argument('model');

        if (is_string($argument) && $argument !== '') {
            return [$argument];
        }

        return array_values((array) config('recommendations.models', []));
    }

    /**
     * @return array<int, int|string|null>
     */
    protected function resolveTenantIds(): array
    {
        $explicit = $this->option('tenant');

        if (is_string($explicit) && $explicit !== '') {
            return [$explicit];
        }

        if (! config('recommendations.tenancy.enabled')) {
            return [null];
        }

        $provider = app()->bound(TenantProvider::class)
            ? app(TenantProvider::class)
            : $this->resolveConfiguredProvider();

        if ($provider instanceof TenantProvider) {
            $ids = $provider->allTenantIds();

            if ($ids !== []) {
                return $ids;
            }
        }

        $this->warn('Multi-tenant enabled but no TenantProvider resolved. Rebuilding for the current tenant only.');

        return [app(TenantResolver::class)->resolveTenantId()];
    }

    protected function resolveConfiguredProvider(): ?TenantProvider
    {
        $provider = config('recommendations.tenancy.tenant_provider');

        if (is_string($provider) && is_subclass_of($provider, TenantProvider::class)) {
            return app($provider);
        }

        return null;
    }

    /**
     * @param  array<int, int|string|null>  $tenantIds
     */
    protected function rebuildModel(string $modelClass, array $tenantIds, int $chunk, ?string $strategy): void
    {
        if (! is_subclass_of($modelClass, Model::class)) {
            $this->error("Skipping [{$modelClass}]: not an Eloquent model.");

            return;
        }

        foreach ($tenantIds as $tenantId) {
            $query = $modelClass::query()->orderBy($modelClass::query()->getModel()->getKeyName());

            $query->chunkById($chunk, function ($records) use ($tenantId, $strategy) {
                foreach ($records as $record) {
                    RecalculateRecommendationsJob::dispatch($record, $strategy, $tenantId);
                }

                $tenantLabel = $tenantId ?? 'default';

                $this->line('Queued '.count($records)." jobs for tenant [{$tenantLabel}]");
            });
        }
    }
}
