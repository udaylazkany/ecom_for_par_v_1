<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Repositories\ProductRepository;
use App\Models\Product;

class TestConcurrencyScenarios extends Command
{
    protected $signature = 'test:concurrency {scenario?} {product_id?}';
    protected $description = 'Test concurrency with and without optimistic locking';

    public function handle(ProductRepository $repo)
    {
        $scenario = $this->argument('scenario') ?? 'both';
        $productId = $this->argument('product_id') ?? 1;
        
        $product = Product::find($productId);
        if (!$product) {
            return;
        }
        
        if ($scenario == 'no_lock' || $scenario == 'both') {
            $this->runScenario($repo, $productId, 'no_lock');
        }
        
        if ($scenario == 'optimistic' || $scenario == 'both') {
            $this->runScenario($repo, $productId, 'optimistic');
        }
    }
    
    private function runScenario($repo, int $productId, string $lockType)
    {
        $initialStock = 100;
        $repo->resetProductForTest($productId, $initialStock, 0);
        
        $repo->runConcurrentPurchases($productId, 1, 100, $lockType);
    }
}