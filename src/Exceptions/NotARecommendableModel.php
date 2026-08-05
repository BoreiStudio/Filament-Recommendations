<?php

namespace BoreiStudio\FilamentRecommendations\Exceptions;

use BoreiStudio\FilamentRecommendations\Contracts\Recommendable;
use BoreiStudio\FilamentRecommendations\Traits\HasRecommendations;
use Exception;

class NotARecommendableModel extends Exception
{
    public static function make(string $model): self
    {
        return new self(
            "Model [{$model}] is not recommendable. Make sure it uses the "
            .HasRecommendations::class.' trait '
            .'and implements '.Recommendable::class.'.'
        );
    }
}
