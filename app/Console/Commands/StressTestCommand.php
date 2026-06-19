<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Repositories\ProductRepository;
use App\Models\Product;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class StressTestCommand extends Command
{
    protected $signature = 'stress:test {concurrent?} {requests?} {mode?}';
    protected $description = 'Stress Test - Modes: no-optimization / with-optimization / compare';

    public function handle(ProductRepository $repo)
    {
        $concurrent = $this->argument('concurrent') ?? 100;
        $requestsPerUser = $this->argument('requests') ?? 1;
        $mode = $this->argument('mode') ?? 'compare';
        
        $this->info("\n=========================================");
        $this->info("     Stress Test");
        $this->info("=========================================");
        $this->info("📊 Users: {$concurrent}");
        $this->info("📊 Requests per user: {$requestsPerUser}");
        $this->info("📊 Total: " . ($concurrent * $requestsPerUser));
        $this->info("🎯 Mode: {$mode}");
        $this->info("=========================================\n");
        
        // Check if product exists
        $product = Product::find(1);
        if (!$product) {
            $this->error("Product with ID 1 not found!");
            $this->info("Please create a product first:");
            $this->info("  php artisan tinker");
            $this->info("  > Product::create(['name'=>'Test','price'=>100,'stock_quantity'=>100,'version'=>0]);");
            return 1;
        }
        
        if ($mode == 'no-optimization' || $mode == 'compare') {
            $this->runStressTest($repo, $concurrent, $requestsPerUser, 'no-optimization');
        }
        
        if ($mode == 'with-optimization' || $mode == 'compare') {
            $this->runStressTest($repo, $concurrent, $requestsPerUser, 'with-optimization');
        }
        
        $this->info("\n=========================================");
        $this->info("✅ Stress Test Completed!");
        $this->info("=========================================\n");
        
        return 0;
    }
    
    private function runStressTest($repo, $concurrent, $requestsPerUser, $mode)
    {
        $modeName = ($mode == 'no-optimization') ? 'Without Optimization' : 'With Optimization';
        
        $this->info("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("📌 Test: {$modeName}");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        
        Log::info("===========================================");
        Log::info("🚀 Starting Stress Test - {$modeName}");
        Log::info("===========================================");
        
        // Clean previous data
        Order::where('customer_name', 'StressTest_User')->delete();
        Cache::forget('products_list');
        
        // Reset product
        $repo->resetProductForTest(1, $concurrent, 0);
        $initialStock = Product::find(1)->stock_quantity;
        
        Log::info("📦 Initial Stock: {$initialStock}");
        
        $results = [
            'total_requests' => 0,
            'success_count' => 0,
            'fail_count' => 0,
            'response_times' => [],
            'errors' => [],
            'start_time' => microtime(true)
        ];
        
        $this->info("🔄 Executing " . ($concurrent * $requestsPerUser) . " requests...");
        
        for ($user = 1; $user <= $concurrent; $user++) {
            for ($req = 1; $req <= $requestsPerUser; $req++) {
                $results['total_requests']++;
                $requestStart = microtime(true);
                
                try {
                    if ($mode == 'no-optimization') {
                        // Without optimization: No Lock (data corruption)
                        $result = $repo->purchaseProductNoLock(1, 1);
                    } else {
                        // With optimization: Transaction + Optimistic Lock (data protection)
                        $result = $repo->purchaseWithTransaction(1, 1, ['customer_name' => 'StressTest_User']);
                    }
                    
                    $requestTime = round((microtime(true) - $requestStart) * 1000, 2);
                    $results['response_times'][] = $requestTime;
                    
                    if ($result['success']) {
                        $results['success_count']++;
                    } else {
                        $results['fail_count']++;
                        $results['errors'][] = "User {$user}, Req {$req}: " . $result['message'];
                    }
                    
                } catch (\Exception $e) {
                    $results['fail_count']++;
                    $results['errors'][] = "User {$user}, Req {$req}: " . $e->getMessage();
                }
            }
        }
        
        $totalTime = round(microtime(true) - $results['start_time'], 2);
        
        // Calculate statistics
        $avgTime = count($results['response_times']) > 0 
            ? round(array_sum($results['response_times']) / count($results['response_times']), 2) : 0;
        $minTime = count($results['response_times']) > 0 ? min($results['response_times']) : 0;
        $maxTime = count($results['response_times']) > 0 ? max($results['response_times']) : 0;
        $successRate = $results['total_requests'] > 0 
            ? round(($results['success_count'] / $results['total_requests']) * 100, 2) : 0;
        
        // Check data integrity
        $finalProduct = Product::find(1);
        $finalStock = $finalProduct->stock_quantity;
        $expectedStock = $initialStock - $results['success_count'];
        $ordersCount = Order::where('customer_name', 'StressTest_User')->count();
        
        $dataIntegrity = ($finalStock == $expectedStock && $ordersCount == $results['success_count']);
        
        // Log results
        Log::info("📊 {$modeName} Results:");
        Log::info("   ✅ Success: {$results['success_count']}");
        Log::info("   ❌ Failed: {$results['fail_count']}");
        Log::info("   📈 Success Rate: {$successRate}%");
        Log::info("   ⏱️  Avg Time: {$avgTime} ms");
        Log::info("   📦 Stock: {$initialStock} → {$finalStock}");
        Log::info("   📋 Orders in DB: {$ordersCount}");
        Log::info("   🔐 Data Integrity: " . ($dataIntegrity ? '✅ PASS' : '❌ FAIL'));
        
        // Display results
        $this->newLine();
        $this->table(
            ['Metric', 'Value'],
            [
                ['✅ Successful Requests', $results['success_count']],
                ['❌ Failed Requests', $results['fail_count']],
                ['📈 Success Rate', "{$successRate}%"],
                ['⏱️  Avg Response Time', "{$avgTime} ms"],
                ['⚡ Min Response Time', "{$minTime} ms"],
                ['🐢 Max Response Time', "{$maxTime} ms"],
                ['⏰ Total Time', "{$totalTime} seconds"],
                ['📊 Requests/Second', round($results['total_requests'] / max($totalTime, 0.01), 2)],
                ['📦 Stock (Before/After)', "{$initialStock} → {$finalStock}"],
                ['📋 Orders in DB', $ordersCount],
                ['🔐 Data Integrity', $dataIntegrity ? '✅ PASS' : '❌ FAIL'],
                ['🏁 System Status', ($dataIntegrity && $results['fail_count'] == 0) ? '✅ Stable' : '❌ Failed/Corrupted'],
            ]
        );
        
        // Additional database details
        $this->newLine();
        $this->info("📊 Database Details:");
        $this->line("   📦 Actual Stock: {$finalStock}");
        $this->line("   📋 Orders Recorded: {$ordersCount}");
        
        if ($mode == 'no-optimization') {
            $lostOrders = $results['success_count'] - $ordersCount;
            if ($lostOrders > 0) {
                $this->error("   🔴 WARNING: Without optimization, stock was deducted but orders were not recorded!");
                $this->error("   🔴 Lost Orders: {$lostOrders} orders missing!");
            } else {
                $this->info("   ⚠️ No race condition detected (maybe load was too low)");
            }
        } else {
            if ($dataIntegrity) {
                $this->info("   ✅ With optimization, stock and orders are perfectly matched!");
            }
        }
        
        // Final summary
        $this->newLine();
        if ($dataIntegrity && $results['fail_count'] == 0) {
            $this->info("🎉 RESULT: System successfully handles {$concurrent} concurrent users!");
            Log::info("🎉 Final Result: SUCCESS - {$concurrent} concurrent users");
        } else {
            $this->error("💥 RESULT: System FAILED to handle {$concurrent} concurrent users!");
            if ($results['fail_count'] > 0) {
                $this->error("   - {$results['fail_count']} operations failed");
            }
            if (!$dataIntegrity) {
                $this->error("   - Data integrity violated!");
                $this->error("   - Stock: {$initialStock} → {$finalStock} (Expected: {$expectedStock})");
                $this->error("   - Orders recorded: {$ordersCount} out of {$results['success_count']}");
            }
            Log::error("💥 Final Result: FAILED - {$concurrent} concurrent users");
        }
        
        // Show first 5 errors
        if (count($results['errors']) > 0) {
            $this->warn("\n⚠️ First 5 Errors:");
            foreach (array_slice($results['errors'], 0, 5) as $error) {
                $this->line("   - {$error}");
            }
            if (count($results['errors']) > 5) {
                $this->line("   - ... and " . (count($results['errors']) - 5) . " more errors");
            }
        }
    }
}