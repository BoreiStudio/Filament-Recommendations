<?php

namespace BoreiStudio\FilamentRecommendations\Exceptions;

use Exception;

class MissingRecommendationsTable extends Exception
{
    public static function make(string $table, string $hint): self
    {
        return new self(
            "The [{$table}] table does not exist. {$hint}"
        );
    }
}
