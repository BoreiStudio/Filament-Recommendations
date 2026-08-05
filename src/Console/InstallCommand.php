<?php

namespace BoreiStudio\FilamentRecommendations\Console;

use BoreiStudio\FilamentRecommendations\Contracts\TenantResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class InstallCommand extends Command
{
    protected $signature = 'recommendations:install
        {--single-tenant : Install in single-tenant mode (no interaction, CI friendly)}
        {--multi-tenant : Install in multi-tenant mode (no interaction, CI friendly)}
        {--tenant-model= : Existing Tenant model class (multi-tenant mode)}
        {--generate-tenant-model : Generate a basic Tenant model when one does not exist (multi-tenant mode)}
        {--super-admin : Also generate the separate Super-Admin panel (multi-tenant mode)}
        {--publish-migrations : Publish migrations immediately}
        {--publish-views : Publish views immediately}
        {--force : Overwrite existing published files}';

    protected $description = 'Install and configure the Filament Recommendations plugin';

    public function handle(): int
    {
        if ($this->option('single-tenant') && $this->option('multi-tenant')) {
            $this->error('Cannot use --single-tenant and --multi-tenant together.');

            return self::FAILURE;
        }

        $multiTenant = $this->resolveTenancy();

        $tenantModel = null;
        $superAdmin = false;

        if ($multiTenant) {
            $tenantModel = $this->resolveTenantModel();
            $superAdmin = $this->resolveSuperAdmin();
        }

        $this->writeConfig($multiTenant);

        if ($multiTenant && $tenantModel && ! class_exists($tenantModel) && $this->resolveGenerateTenantModel($tenantModel)) {
            $this->generateTenantModel($tenantModel);
        }

        if ($multiTenant && $superAdmin) {
            $this->generatePanels();
        }

        $publishMigrations = $this->resolvePublish('migrations');
        $publishViews = $this->resolvePublish('views');

        if ($publishMigrations) {
            $this->publishMigrations();
        }

        if ($publishViews) {
            $this->publishViews();
        }

        $this->printSummary($multiTenant, $tenantModel, $superAdmin, $publishMigrations, $publishViews);

        return self::SUCCESS;
    }

    protected function resolveTenancy(): bool
    {
        if ($this->option('single-tenant')) {
            return false;
        }

        if ($this->option('multi-tenant')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            $this->info('Non-interactive run without --single-tenant/--multi-tenant, defaulting to single-tenant.');

            return false;
        }

        return select(
            label: 'How will this project use tenancy?',
            options: [
                'single' => 'Single-tenant (one implicit tenant, simpler)',
                'multi' => 'Multi-tenant (separate data per tenant)',
            ],
            default: 'single',
        ) === 'multi';
    }

    protected function resolveTenantModel(): string
    {
        $model = $this->option('tenant-model');

        if (! $model && $this->input->isInteractive()) {
            $model = text(
                label: 'What is the existing Tenant model class?',
                placeholder: 'App\\Models\\Tenant',
                required: true,
                hint: 'Leave the default to generate a basic Tenant model automatically.',
            );
        }

        return $model ?: 'App\Models\Tenant';
    }

    protected function resolveGenerateTenantModel(string $tenantModel): bool
    {
        if ($this->option('generate-tenant-model')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            $this->warn("Tenant model {$tenantModel} does not exist. Generating a basic one.");

            return true;
        }

        return confirm(
            label: "Tenant model {$tenantModel} does not exist. Generate a basic one?",
            default: true,
        );
    }

    protected function resolveSuperAdmin(): bool
    {
        if ($this->option('super-admin')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            $this->info('Non-interactive run, skipping the Super-Admin panel. Use --super-admin to generate it.');

            return false;
        }

        return confirm(
            label: 'Generate a separate Super-Admin panel, apart from the Tenant panel?',
            default: false,
        );
    }

    protected function resolvePublish(string $what): bool
    {
        $flag = "publish-{$what}";

        if ($this->option($flag)) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            return false;
        }

        return confirm(
            label: "Publish {$what} now?",
            default: false,
        );
    }

    protected function writeConfig(bool $multiTenant): void
    {
        $configPath = config_path('recommendations.php');

        $this->call('vendor:publish', [
            '--tag' => 'filament-recommendations-config',
            '--force' => $this->option('force') ? true : false,
        ]);

        $contents = file_get_contents($configPath);

        $contents = preg_replace(
            "/'enabled' => (true|false), \/\/ recommendations\.tenancy\.enabled/",
            "'enabled' => ".var_export($multiTenant, true).', // recommendations.tenancy.enabled',
            $contents,
        );

        file_put_contents($configPath, $contents);

        $this->info('Config published to config/recommendations.php');
    }

    protected function generateTenantModel(string $tenantModel): void
    {
        $class = Str::afterLast($tenantModel, '\\');
        $namespace = Str::beforeLast($tenantModel, '\\');
        $relativePath = Str::after($tenantModel, 'App\\');
        $target = app_path(str_replace('\\', DIRECTORY_SEPARATOR, $relativePath).'.php');

        if (! $this->confirmOverwrite($target)) {
            return;
        }

        $stub = file_get_contents(__DIR__.'/../../stubs/TenantModel.stub');
        $stub = Str::replace(
            ['{{ namespace }}', '{{ class }}'],
            [$namespace, $class],
            $stub,
        );

        $this->ensureDirectory(dirname($target));
        file_put_contents($target, $stub);

        $this->info("Generated Tenant model at {$target}");
    }

    protected function generatePanels(): void
    {
        $stubs = [
            'TenantPanelProvider.stub' => app_path('Providers/Filament/TenantPanelProvider.php'),
            'SuperAdminPanelProvider.stub' => app_path('Providers/Filament/SuperAdminPanelProvider.php'),
        ];

        foreach ($stubs as $stub => $target) {
            if (! $this->confirmOverwrite($target)) {
                continue;
            }

            $content = file_get_contents(__DIR__.'/../../stubs/'.$stub);
            $this->ensureDirectory(dirname($target));
            file_put_contents($target, $content);

            $this->info("Generated {$target}");
        }
    }

    protected function publishMigrations(): void
    {
        $this->call('vendor:publish', [
            '--tag' => 'filament-recommendations-migrations',
            '--force' => $this->option('force') ? true : false,
        ]);

        $this->info('Migrations published to database/migrations');
    }

    protected function publishViews(): void
    {
        if (! is_dir(__DIR__.'/../../resources/views')) {
            $this->warn('No views to publish yet in this version of the package.');

            return;
        }

        $this->call('vendor:publish', [
            '--tag' => 'filament-recommendations-views',
            '--force' => $this->option('force') ? true : false,
        ]);

        $this->info('Views published to resources/views/vendor/filament-recommendations');
    }

    protected function confirmOverwrite(string $target): bool
    {
        if (! file_exists($target)) {
            return true;
        }

        if ($this->option('force')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            $this->warn("{$target} already exists, skipping (use --force to overwrite).");

            return false;
        }

        return confirm(
            label: "{$target} already exists. Overwrite?",
            default: false,
        );
    }

    protected function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    protected function printSummary(
        bool $multiTenant,
        ?string $tenantModel,
        bool $superAdmin,
        bool $publishMigrations,
        bool $publishViews,
    ): void {
        $this->newLine();
        $this->components->info('Recommendations plugin installed successfully.');

        $this->components->twoColumnDetail('Tenancy', $multiTenant ? 'Multi-tenant' : 'Single-tenant');

        if ($multiTenant) {
            $this->components->twoColumnDetail('Tenant model', $tenantModel);
            $this->components->twoColumnDetail('Super-Admin panel', $superAdmin ? 'Generated' : 'Not generated');
        }

        $this->components->twoColumnDetail('Migrations', $publishMigrations ? 'Published' : 'Not published');
        $this->components->twoColumnDetail('Views', $publishViews ? 'Published' : 'Not published');

        $this->newLine();
        $this->components->info('Next steps:');

        if ($superAdmin) {
            $this->line('  - Register App\\Providers\\Filament\\TenantPanelProvider and App\\Providers\\Filament\\SuperAdminPanelProvider in bootstrap/providers.php');
        } else {
            $this->line('  - Register App\\Providers\\Filament\\TenantPanelProvider in bootstrap/providers.php');
        }

        if ($multiTenant) {
            $this->line('  - Implement '.TenantResolver::class.' and bind it in config/recommendations.php');
        }

        if ($publishMigrations) {
            $this->line('  - Run php artisan migrate');
        }

        $this->line('  - Add the HasRecommendations trait to your models and use the RecommendationsWidget (see README)');
    }
}
