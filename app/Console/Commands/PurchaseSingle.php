<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Repositories\ProductRepository;

class PurchaseSingle extends Command
{
    protected $signature = 'purchase:single {product_id} {quantity} {use_lock?}';
    protected $description = 'Execute single purchase for parallel testing';

    public function handle(ProductRepository $repo)
    {
        $productId = (int) $this->argument('product_id');
        $quantity = (int) $this->argument('quantity');
        $useLock = $this->argument('use_lock') === 'lock';

        if ($useLock) {
            $repo->purchaseProductWithDistributedLock($productId, $quantity);
        } else {
            $repo->purchaseProductNoLock($productId, $quantity);
        }

        return 0;
    }
}