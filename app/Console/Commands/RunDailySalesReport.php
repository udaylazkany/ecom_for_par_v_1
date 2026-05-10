<?php

namespace App\Console\Commands;

use App\Jobs\DailySalesReportJob;
use Illuminate\Console\Command;

class RunDailySalesReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:run-daily-sales-report';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
         DailySalesReportJob::dispatch();
    $this->info("DailySalesReportJob dispatched manually.");
    }
}
