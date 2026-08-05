<?php

namespace BoreiStudio\FilamentRecommendations\Models;

use BoreiStudio\FilamentRecommendations\Models\Concerns\ScopedByTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $tenant_id
 * @property string|null $source_type
 * @property int|string|null $source_id
 * @property string|null $recommended_type
 * @property int|string|null $recommended_id
 * @property float $score
 * @property string $strategy
 * @property Carbon|null $computed_at
 */
class Recommendation extends Model
{
    use ScopedByTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'string',
            'score' => 'float',
            'computed_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function recommended(): MorphTo
    {
        return $this->morphTo();
    }
}
