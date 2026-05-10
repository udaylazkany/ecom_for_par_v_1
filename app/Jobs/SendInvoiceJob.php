<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
      public $orderId;
    public function __construct($orderId)
    {
         $this->orderId = $orderId; 
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
          sleep(2);

        \Log::info("Invoice sent for order {$this->orderId}");
    }
}
