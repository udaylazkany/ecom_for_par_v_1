<?php

namespace App\Jobs;

use App\LoadBalancing\LoadBalancer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LoadBalancedFetchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $backoff = 30;
    public ?string $serverName = null; 

    public function __construct(?string $serverName = null) 
    {
        $this->serverName = $serverName;
    }

    public function handle()
    {
        if ($this->serverName === null) {
            $loadBalancer = app(LoadBalancer::class);
            $this->serverName = $loadBalancer->getNextServer();
        }
        
        $server = new \App\LoadBalancing\Server($this->serverName);
        $server->fetchAllProducts();
    }
}