<?php

namespace BoreiStudio\FilamentRecommendations\Models;

use BoreiStudio\FilamentRecommendations\Models\Concerns\ScopedByTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

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
