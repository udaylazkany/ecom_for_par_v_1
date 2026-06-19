<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Repositories\ProductRepository;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

class TestTransactionIntegrity extends Command
{
    protected $signature = 'test:transaction {mode?} {product_id?}';
    protected $description = 'Test transaction integrity (ACID) - Options: without, with, both';

    public function handle(ProductRepository $repo)
    {
        $mode = $this->argument('mode') ?? 'both';
        $productId = $this->argument('product_id') ?? 1;
        
        // Check if product exists
        $product = Product::find($productId);
        if (!$product) {
            $this->error("Product not found with ID: {$productId}");
            $this->info("Please create a product first using: php artisan tinker");
            $this->info("Then type: Product::create(['name'=>'test','price'=>100,'stock_quantity'=>100,'version'=>0]);");
            return 1;
        }
        
        Log::info("===========================================");
        Log::info("🔬 Starting Transaction Integrity Test (ACID)");
        Log::info("📦 Product: {$product->name} (ID: {$productId})");
        Log::info("💰 Price: {$product->price}");
        Log::info("📊 Current Stock: {$product->stock_quantity}");
        Log::info("===========================================");
        
        $this->info("\n🔬 ========== Transaction Integrity Test (ACID) ==========\n");
        $this->info("📦 Product: {$product->name} (ID: {$productId})");
        $this->info("💰 Price: {$product->price}");
        $this->info("📊 Current Stock: {$product->stock_quantity}");
        
        if ($mode == 'without' || $mode == 'both') {
            $this->info("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("📝 Test: Without Transaction");
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->testScenarioWithoutTransaction($repo, $productId, 5, ['customer_name' => 'Test_User', 'simulate_failure' => true]);
        }
        
        if ($mode == 'with' || $mode == 'both') {
            $this->info("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("📝 Test: With Transaction");
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->testScenarioWithTransaction($repo, $productId, 5, ['customer_name' => 'Test_User', 'simulate_failure' => true]);
        }
        
        Log::info("✅ Transaction Integrity Test Completed");
        Log::info("===========================================");
        
        $this->info("\n✅ Test completed!\n");
    }
    
    private function testScenarioWithoutTransaction($repo, $productId, $quantity, $orderData)
    {
        Log::info("---------- WITHOUT Transaction Test ----------");
        Log::info("Product ID: {$productId}, Quantity: {$quantity}");
        Log::info("Simulate Failure: " . (($orderData['simulate_failure'] ?? false) ? 'Yes' : 'No'));
        
        // Reset product
        $repo->resetProductForTest($productId, 100, 0);
        
        $this->info("\n🔄 Product reset: Stock = 100");
        $this->info("\n📌 Scenario: Without Transaction");
        
        $start = microtime(true);
        try {
            $result = $repo->purchaseWithoutTransaction($productId, $quantity, $orderData);
        } catch (\Exception $e) {
            $result = ['success' => false, 'error' => $e->getMessage()];
        }
        $time = round((microtime(true) - $start) * 1000, 2);
        
        // Log results
        Log::info("WITHOUT Transaction Result: " . ($result['success'] ? 'Success ✅' : 'Failed ❌'));
        if (!$result['success']) {
            Log::error("Failure Reason: " . ($result['error'] ?? $result['message'] ?? 'Unknown error'));
        }
        
        // Display results
        if ($result['success']) {
            $this->info("   ✅ Operation succeeded");
            $this->line("   📦 Final Stock: " . ($result['final_stock'] ?? '?'));
            $this->line("   📋 Order ID: " . ($result['order_id'] ?? '?'));
            Log::info("Final Stock: " . ($result['final_stock'] ?? '?'));
            Log::info("Order ID: " . ($result['order_id'] ?? '?'));
        } else {
            $this->error("   ❌ Operation failed: " . ($result['error'] ?? $result['message'] ?? 'Unknown error'));
            $product = Product::find($productId);
            $this->line("   📦 Final Stock: " . ($product->stock_quantity ?? '?'));
            Log::warning("Final Stock: " . ($product->stock_quantity ?? '?'));
        }
        $this->line("   ⏱️  Time taken: {$time} ms");
        Log::info("Time taken: {$time} ms");
        
        // Analysis
        $this->line("\n📊 Analysis:");
        $product = Product::find($productId);
        
        if ($product->stock_quantity == 95) {
            $this->error("   🔴 Issue: Stock decreased from 100 to 95 even though the operation failed!");
            $this->error("   🔴 This means: Data corruption (stock decreased without creating an order)");
            Log::critical("🔴 Issue: Data corruption! Stock decreased from 100 to {$product->stock_quantity} despite failure");
        } else {
            $this->info("   ✅ Stock unchanged (data integrity preserved)");
            Log::info("✅ Stock unchanged (data integrity preserved)");
        }
        
        Log::info("---------- WITHOUT Transaction Test Completed ----------");
    }
    
    private function testScenarioWithTransaction($repo, $productId, $quantity, $orderData)
    {
        Log::info("---------- WITH Transaction Test ----------");
        Log::info("Product ID: {$productId}, Quantity: {$quantity}");
        Log::info("Simulate Failure: " . (($orderData['simulate_failure'] ?? false) ? 'Yes' : 'No'));
        
        // Reset product
        $repo->resetProductForTest($productId, 100, 0);
        
        $this->info("\n🔄 Product reset: Stock = 100");
        $this->info("\n📌 Scenario: With Transaction");
        
        $start = microtime(true);
        try {
            $result = $repo->purchaseWithTransaction($productId, $quantity, $orderData);
        } catch (\Exception $e) {
            $result = ['success' => false, 'error' => $e->getMessage()];
        }
        $time = round((microtime(true) - $start) * 1000, 2);
        
        // Log results
        Log::info("WITH Transaction Result: " . ($result['success'] ? 'Success ✅' : 'Failed ❌'));
        if (!$result['success']) {
            Log::error("Failure Reason: " . ($result['error'] ?? $result['message'] ?? 'Unknown error'));
        }
        
        // Display results
        if ($result['success']) {
            $this->info("   ✅ Operation succeeded");
            $this->line("   📦 Final Stock: " . ($result['final_stock'] ?? '?'));
            $this->line("   📋 Order ID: " . ($result['order_id'] ?? '?'));
            Log::info("Final Stock: " . ($result['final_stock'] ?? '?'));
            Log::info("Order ID: " . ($result['order_id'] ?? '?'));
        } else {
            $this->error("   ❌ Operation failed: " . ($result['error'] ?? $result['message'] ?? 'Unknown error'));
            $product = Product::find($productId);
            $this->line("   📦 Final Stock: " . ($product->stock_quantity ?? '?'));
            Log::warning("Final Stock: " . ($product->stock_quantity ?? '?'));
        }
        $this->line("   ⏱️  Time taken: {$time} ms");
        Log::info("Time taken: {$time} ms");
        
        // Analysis
        $this->line("\n📊 Analysis:");
        $product = Product::find($productId);
        
        if ($product->stock_quantity == 100) {
            $this->info("   ✅ Stock unchanged (Rollback successful)");
            $this->info("   ✅ Data integrity 100% preserved");
            Log::info("✅ Stock unchanged (Rollback successful) - Data integrity 100% preserved");
        } else {
            $this->error("   🔴 Stock changed from 100 to {$product->stock_quantity} despite failure!");
            Log::critical("🔴 Stock changed from 100 to {$product->stock_quantity} despite failure!");
        }
        
        Log::info("---------- WITH Transaction Test Completed ----------");
    }
}