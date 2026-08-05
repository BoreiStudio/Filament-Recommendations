<?php

namespace BoreiStudio\FilamentRecommendations\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A plain Eloquent model that does NOT use the HasRecommendations trait.
 * Used to prove the RecommendationService and jobs work with any model.
 */
class PlainProduct extends Model
{
    protected $table = 'products';

    protected $guarded = [];
}
