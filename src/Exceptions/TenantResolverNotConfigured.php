<?php

namespace BoreiStudio\FilamentRecommendations\Exceptions;

use BoreiStudio\FilamentRecommendations\Contracts\TenantResolver;
use Exception;

class TenantResolverNotConfigured extends Exception
{
    public static function create(): self
    {
        return new self(
            'Multi-tenant mode is enabled but no TenantResolver is configured. '
            .'Set recommendations.tenancy.resolver in config/recommendations.php to a class implementing '
            .TenantResolver::class.' (code 0)', 0
        );
    }
}
