<?php

namespace BoreiStudio\FilamentRecommendations\Models;

use BoreiStudio\FilamentRecommendations\Models\Concerns\ScopedByTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $tenant_id
 * @property string|null $subject_type
 * @property int|string|null $subject_id
 * @property string|null $related_type
 * @property int|string|null $related_id
 * @property string|null $actor_type
 * @property int|string|null $actor_id
 * @property string $action
 * @property Carbon|null $created_at
 */
class RecommendationEvent extends Model
{
    use ScopedByTenant;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'string',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The user / actor who performed the interaction (used by collaborative
     * filtering).
     *
     * @return MorphTo<Model, $this>
     */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }
}
