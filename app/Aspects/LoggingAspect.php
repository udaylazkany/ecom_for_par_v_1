<?php

namespace App\Aspects;

use Illuminate\Support\Facades\Log;

class LoggingAspect
{
    public static function wrap(string $label, callable $callback)
    {
        \Log::info("Before LoggingAspect");
        \Log::info("AOP: {$label} started");

        $result = $callback();

        \Log::info("AOP: {$label} finished");

        return $result;
    }
}
