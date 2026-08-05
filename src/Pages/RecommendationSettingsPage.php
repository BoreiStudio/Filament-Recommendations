<?php

namespace BoreiStudio\FilamentRecommendations\Pages;

use BackedEnum;
use BoreiStudio\FilamentRecommendations\Clusters\RecommendationsConfigCluster;
use BoreiStudio\FilamentRecommendations\Contracts\TenantResolver;
use BoreiStudio\FilamentRecommendations\Models\Recommendation;
use BoreiStudio\FilamentRecommendations\Models\RecommendationSetting;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

/**
 * @property Schema $form
 */
class RecommendationSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog;

    protected Width|string|null $maxContentWidth = Width::ScreenTwoExtraLarge;

    protected string $view = 'recommendations::pages.recommendation-settings';

    public static function getCluster(): ?string
    {
        return RecommendationsConfigCluster::class;
    }

    /**
     * @var array{tenant_id?: int|string|null, settings?: array<int, array{model: string, strategy?: string|null, limit?: int|null}>}|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $tenantId = app(TenantResolver::class)->resolveTenantId();

        $stored = RecommendationSetting::query()
            ->get()
            ->keyBy('model');

        $settings = [];

        foreach ((array) config('recommendations.models', []) as $model) {
            $settings[] = [
                'model' => $model,
                'strategy' => $stored->get($model)?->strategy,
                'limit' => $stored->get($model)?->limit,
            ];
        }

        $this->form->fill([
            'tenant_id' => $tenantId,
            'settings' => $settings,
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->statePath('data')
            ->schema([
                Section::make(__('filament-recommendations::messages.pages.recommendation-settings.models_section'))
                    ->footerActions([
                        Action::make('save')
                            ->label(__('filament-recommendations::messages.pages.recommendation-settings.save'))
                            ->submit('save')
                            ->color('primary'),
                    ])
                    ->schema([
                        Repeater::make('settings')
                            ->schema([
                                TextInput::make('model')
                                    ->label(__('filament-recommendations::messages.pages.recommendation-settings.models_section'))
                                    ->disabled()
                                    ->dehydrated(),
                                Select::make('strategy')
                                    ->label(__('filament-recommendations::messages.pages.recommendation-settings.strategy'))
                                    ->helperText(__('filament-recommendations::messages.pages.recommendation-settings.strategy_helper'))
                                    ->options($this->getStrategies())
                                    ->placeholder(__('filament-recommendations::messages.pages.recommendation-settings.strategy_default', ['strategy' => $this->getDefaultStrategyName()]))
                                    ->native(false),
                                TextInput::make('limit')
                                    ->label(__('filament-recommendations::messages.pages.recommendation-settings.limit'))
                                    ->helperText(__('filament-recommendations::messages.pages.recommendation-settings.limit_helper'))
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(100),
                            ])
                            ->columns(3)
                            ->reorderable(false)
                            ->addable(false)
                            ->deletable(false),
                    ]),
            ]);
    }

    public function save(): void
    {
        $tenantId = $this->data['tenant_id'] ?? null;

        foreach ($this->data['settings'] ?? [] as $config) {
            RecommendationSetting::query()
                ->updateOrCreate(
                    ['model' => $config['model'], 'tenant_id' => $tenantId],
                    ['strategy' => ($config['strategy'] ?? null) ?: null, 'limit' => ($config['limit'] ?? null) ?: null],
                );
        }

        Notification::make()
            ->title(__('filament-recommendations::messages.pages.recommendation-settings.saved'))
            ->success()
            ->send();
    }

    /**
     * @return array<string, string>
     */
    public function getStrategies(): array
    {
        $strategies = [];

        foreach ((array) config('recommendations.strategies.registry', []) as $strategy) {
            $strategies[$strategy] = class_basename($strategy);
        }

        return $strategies;
    }

    public function getDefaultStrategyName(): string
    {
        return class_basename(config('recommendations.strategies.default'));
    }

    public function getTenantLabel(): string
    {
        $tenantId = $this->data['tenant_id'] ?? null;

        return $tenantId
            ? __('filament-recommendations::messages.pages.recommendation-settings.tenant', ['tenant' => $tenantId])
            : __('filament-recommendations::messages.pages.recommendation-settings.default_tenant');
    }

    public function getLastRebuildAt(): ?string
    {
        $latest = Recommendation::query()->max('computed_at');

        return $latest ? Carbon::parse($latest)->diffForHumans() : null;
    }
}
