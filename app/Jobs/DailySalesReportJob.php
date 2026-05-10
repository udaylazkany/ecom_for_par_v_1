<?php

namespace App\Jobs;

use App\Models\order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Aspects\PerformanceAspect;
use App\Aspects\LoggingAspect;
use App\Aspects\QueryAspect;
use App\Aspects\LockAspect;

class DailySalesReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        //
    }

    public function handle(): void
{
    LoggingAspect::wrap("Daily Report", function () {

        PerformanceAspect::measure("Daily Report Calculation", function () {

            QueryAspect::track("Daily Report Query", function () {

                LockAspect::once("daily_report_lock", 30, function () {

                    \Log::info("Job started");

                    $total = 0;

                    foreach (order::whereDate('created_at', today())->cursor() as $order) {
                        $total += $order->total_amount;
                    }

                    \Log::info("Job finished with total = {$total}");
                });
            });
        });
    });
}

}
