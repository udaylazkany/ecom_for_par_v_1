<?php

namespace App\Aspects;

use Illuminate\Support\Facades\Log;

class PerformanceAspect
{
    public static function measure(string $label, callable $callback)
    {
        $start = microtime(true);

        $result = $callback();

        $duration = round((microtime(true) - $start) * 1000, 2);

        Log::info("AOP: {$label} took {$duration} ms");

        return $result;
    }
}
