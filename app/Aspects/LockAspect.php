<?php

namespace App\Aspects;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LockAspect
{
    public static function once(string $key, int $seconds, callable $callback)
    {
        $lock = Cache::lock($key, $seconds);

        return $lock->get(function () use ($callback, $key) {
            \Log::info("AOP: Lock acquired for {$key}");
            return $callback();
        });
    }
}
