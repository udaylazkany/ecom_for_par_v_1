<?php

namespace App\Aspects;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QueryAspect
{
    public static function track(string $label, callable $callback)
    {
        DB::enableQueryLog();

        $result = $callback();

        $queries = DB::getQueryLog();

        \Log::info("AOP: {$label} executed " . count($queries) . " queries");

        return $result;
    }
}
