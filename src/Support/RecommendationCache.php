<?php

namespace BoreiStudio\FilamentRecommendations\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Cache for the cached recommendations of a subject model.
 *
 * Only scalar identifiers ([type, id]) are stored — Eloquent models do not
 * survive a persistent cache round-trip. The rehydrate callback rebuilds the
 * actual models when the cache is read.
 */
class RecommendationCache
{
    /**
     * @param  Closure(): array<int, array{0: string, 1: int|string}>  $callback
     * @param  Closure(array<int, array{0: string, 1: int|string}>): Collection<int, Model>  $rehydrate
     * @return Collection<int, Model>
     */
    public static function remember(Model $subject, int|string|null $tenantId, Closure $callback, Closure $rehydrate): Collection
    {
        if (! config('recommendations.cache.enabled', true)) {
            return $rehydrate($callback());
        }

        $store = config('recommendations.cache.store');
        $ttl = (int) config('recommendations.cache.ttl', 3600);

        $cache = $store ? Cache::store($store) : Cache::store();

        $ids = $cache->remember(self::key($subject, $tenantId), $ttl, $callback);

        return $rehydrate($ids);
    }

    public static function forget(Model $subject, int|string|null $tenantId): void
    {
        if (! config('recommendations.cache.enabled', true)) {
            return;
        }

        $store = config('recommendations.cache.store');

        ($store ? Cache::store($store) : Cache::store())->forget(self::key($subject, $tenantId));
    }

    public static function key(Model $subject, int|string|null $tenantId): string
    {
        return implode(':', [
            'filament-recommendations',
            $subject->getMorphClass(),
            $subject->getKey(),
            $tenantId ?? 'default',
        ]);
    }
}
