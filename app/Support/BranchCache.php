<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Closure;
use DateTimeInterface;

class BranchCache
{
    /**
     * Dapatkan versi cache tenant bisnis saat ini (default: 1).
     */
    public static function version(int $businessId): int
    {
        return (int) Cache::rememberForever("biz_{$businessId}_cache_ver", function () {
            return 1;
        });
    }

    /**
     * Susun kunci cache terisolasi per tenant, versi, dan scope cabang.
     * Format: biz_{businessId}_v{version}_{scope}_{name}_{parts...}
     */
    public static function key(string $name, ...$parts): string
    {
        /** @var BranchContext $context */
        $context = app(BranchContext::class);

        $businessId = $context->getBusinessId() ?? (Auth::check() ? Auth::user()->business_id : 0);
        $ver = self::version((int) $businessId);

        if ($context->isBranch() && $context->getBranchId() !== null) {
            $scope = 'b' . $context->getBranchId();
        } else {
            $scope = 'all';
        }

        $cleanParts = array_map(function ($part) {
            if ($part instanceof \DateTimeInterface) {
                return $part->format('Y-m-d');
            }
            return (string) $part;
        }, $parts);

        $partsStr = !empty($cleanParts) ? '_' . implode('_', $cleanParts) : '';

        return "biz_{$businessId}_v{$ver}_{$scope}_{$name}{$partsStr}";
    }

    /**
     * Bungkus Cache::remember dengan key terisolasi multi-cabang.
     */
    public static function remember(string $name, int|DateTimeInterface $ttl, Closure $callback, ...$parts): mixed
    {
        $cacheKey = self::key($name, ...$parts);
        return Cache::remember($cacheKey, $ttl, $callback);
    }

    /**
     * Hapus / invalidasi seluruh cache untuk business_id dengan meng-increment nomor versi.
     */
    public static function flush(int $businessId): void
    {
        if ($businessId <= 0) {
            return;
        }

        $key = "biz_{$businessId}_cache_ver";
        if (Cache::has($key)) {
            Cache::increment($key);
        } else {
            Cache::forever($key, 2);
        }
    }
}

