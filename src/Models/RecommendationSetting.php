<?php

namespace BoreiStudio\FilamentRecommendations\Models;

use BoreiStudio\FilamentRecommendations\Models\Concerns\ScopedByTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Per-tenant (or global, when tenant_id is null) configuration for a
 * recommendable model: which strategy to use and how many results to return.
 */
/**
 * @property int $id
 * @property string|null $tenant_id
 * @property string $model
 * @property string|null $strategy
 * @property int|null $limit
 */
class RecommendationSetting extends Model
{
    use ScopedByTenant;

    protected $fillable = ['tenant_id', 'model', 'strategy', 'limit'];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'string',
            'limit' => 'integer',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function for(string $model, int|string|null $tenantId = null): array
    {
        $query = static::query()
            ->withoutGlobalScope('recommendation_tenant')
            ->where('model', $model);

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereNull('tenant_id');
        }

        return $query->first()?->toArray() ?? [];
    }

    public static function strategyFor(string $model, int|string|null $tenantId = null): ?string
    {
        return Arr::get(static::for($model, $tenantId), 'strategy');
    }

    public static function limitFor(string $model, int|string|null $tenantId = null): ?int
    {
        return Arr::get(static::for($model, $tenantId), 'limit');
    }
}
