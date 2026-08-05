<?php

namespace BoreiStudio\FilamentRecommendations\Tests\Fixtures;

use BoreiStudio\FilamentRecommendations\Contracts\Recommendable;
use BoreiStudio\FilamentRecommendations\Traits\HasRecommendations;
use Illuminate\Database\Eloquent\Model;

class Article extends Model implements Recommendable
{
    use HasRecommendations;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
        ];
    }

    public function getRecommendationFeatures(): array
    {
        return [
            'category' => $this->category,
            'brand' => $this->brand,
            'price' => (float) ($this->price ?? 0),
            'tags' => $this->tags ?? [],
        ];
    }

    public function getRecommendationTenantId(): int|string|null
    {
        return $this->tenant_id ?? null;
    }
}
