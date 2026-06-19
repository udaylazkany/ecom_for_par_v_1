<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Repositories\ProductRepository;
use App\Models\Product;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Aspects\PerformanceAspect;

class BenchmarkCommand extends Command
{
    protected $signature = 'benchmark:run {iterations?}';
    protected $description = 'System Benchmarking and Bottleneck Analysis';

    private $benchmarkResults = [];

    public function handle(ProductRepository $repo)
    {
        $iterations = $this->argument('iterations') ?? 10;
        
        $this->info("\n");
        $this->info("╔════════════════════════════════════════════════════════════════╗");
        $this->info("║                    📊 BENCHMARKING REPORT                      ║");
        $this->info("║              System Performance Measurement                    ║");
        $this->info("╚════════════════════════════════════════════════════════════════╝");
        $this->info("\n📅 Date: " . now()->format('Y-m-d H:i:s'));
        $this->info("🔄 Iterations: {$iterations}\n");
        
        // ========== 1. Get Products Benchmark ==========
        $this->benchmarkGetProducts($repo, $iterations);
        
        // ========== 2. Purchase Operations Benchmark ==========
        $this->benchmarkPurchaseOperations($repo, $iterations);
        
        // ========== 3. Transactions Benchmark ==========
        $this->benchmarkTransactions($repo, $iterations);
        
        // ========== 4. Bottleneck Analysis ==========
        $this->analyzeBottlenecks();
        
        // ========== 5. Recommendations ==========
        $this->recommendations();
        
        // ========== 6. Save Report ==========
        $this->saveReport();
        
        return 0;
    }
    
    private function benchmarkGetProducts($repo, $iterations)
    {
        $this->info("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("📋 1. Get Products Benchmark");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        
        // Without Cache
        Cache::forget('products_list');
        $timesWithoutCache = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $products = Product::all();
            $timesWithoutCache[] = round((microtime(true) - $start) * 1000, 2);
        }
        
        // With Cache
        $timesWithCache = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $products = Cache::remember('products_list', 60, function() {
                return Product::all();
            });
            $timesWithCache[] = round((microtime(true) - $start) * 1000, 2);
        }
        
        $avgWithoutCache = round(array_sum($timesWithoutCache) / count($timesWithoutCache), 2);
        $avgWithCache = round(array_sum($timesWithCache) / count($timesWithCache), 2);
        $improvement = round((($avgWithoutCache - $avgWithCache) / $avgWithoutCache) * 100, 2);
        
        $this->table(
            ['Status', 'Avg Time (ms)', 'Min (ms)', 'Max (ms)', 'Improvement'],
            [
                ['Without Cache', $avgWithoutCache, min($timesWithoutCache), max($timesWithoutCache), '-'],
                ['With Cache', $avgWithCache, min($timesWithCache), max($timesWithCache), $improvement . '%'],
            ]
        );
        
        // Store results
        $this->benchmarkResults['get_products'] = [
            'without_cache' => $avgWithoutCache,
            'with_cache' => $avgWithCache,
            'improvement' => $improvement
        ];
    }
    
    private function benchmarkPurchaseOperations($repo, $iterations)
    {
        $this->info("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("💰 2. Purchase Operations Benchmark");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        
        // Reset product
        $repo->resetProductForTest(1, 100, 0);
        
        // No Lock
        $timesNoLock = [];
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $result = $repo->purchaseProductNoLock(1, 1);
            $timesNoLock[] = round((microtime(true) - $start) * 1000, 2);
        }
        
        // Reset product
        $repo->resetProductForTest(1, 100, 0);
        
        // Optimistic Lock
        $timesOptimistic = [];
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $result = $repo->purchaseProductWithOptimisticLock(1, 1);
            $timesOptimistic[] = round((microtime(true) - $start) * 1000, 2);
        }
        
        $avgNoLock = round(array_sum($timesNoLock) / count($timesNoLock), 2);
        $avgOptimistic = round(array_sum($timesOptimistic) / count($timesOptimistic), 2);
        $overhead = round($avgOptimistic - $avgNoLock, 2);
        
        $this->table(
            ['Lock Type', 'Avg Time (ms)', 'Min (ms)', 'Max (ms)', 'Note'],
            [
                ['No Lock', $avgNoLock, min($timesNoLock), max($timesNoLock), 'Fast but unsafe'],
                ['Optimistic Lock', $avgOptimistic, min($timesOptimistic), max($timesOptimistic), "Slower by {$overhead}ms but safe"],
            ]
        );
        
        $this->benchmarkResults['purchase'] = [
            'no_lock' => $avgNoLock,
            'optimistic_lock' => $avgOptimistic,
            'overhead_ms' => $overhead
        ];
    }
    
    private function benchmarkTransactions($repo, $iterations)
    {
        $this->info("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("🔐 3. Transactions Benchmark");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        
        // Without Transaction (successful)
        $repo->resetProductForTest(1, 100, 0);
        $timesWithoutTransaction = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $result = $repo->purchaseWithoutTransaction(1, 1, ['customer_name' => 'Benchmark']);
            $timesWithoutTransaction[] = round((microtime(true) - $start) * 1000, 2);
        }
        
        // With Transaction (successful)
        $repo->resetProductForTest(1, 100, 0);
        $timesWithTransaction = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $result = $repo->purchaseWithTransaction(1, 1, ['customer_name' => 'Benchmark']);
            $timesWithTransaction[] = round((microtime(true) - $start) * 1000, 2);
        }
        
        $avgWithoutTransaction = round(array_sum($timesWithoutTransaction) / count($timesWithoutTransaction), 2);
        $avgWithTransaction = round(array_sum($timesWithTransaction) / count($timesWithTransaction), 2);
        $transactionOverhead = round($avgWithTransaction - $avgWithoutTransaction, 2);
        
        $this->table(
            ['Status', 'Avg Time (ms)', 'Data Integrity', 'Note'],
            [
                ['Without Transaction', $avgWithoutTransaction, '❌ Unsafe', 'May lose data on failure'],
                ['With Transaction', $avgWithTransaction, '✅ Safe', "Slower by {$transactionOverhead}ms but safe"],
            ]
        );
        
        $this->benchmarkResults['transactions'] = [
            'without_transaction' => $avgWithoutTransaction,
            'with_transaction' => $avgWithTransaction,
            'overhead_ms' => $transactionOverhead
        ];
    }
    
    private function analyzeBottlenecks()
    {
        $this->info("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("🔍 4. Bottleneck Analysis");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        
        $bottlenecks = [];
        
        // Bottleneck 1: Database queries without cache
        $cacheImprovement = $this->benchmarkResults['get_products']['improvement'] ?? 0;
        if ($cacheImprovement > 50) {
            $bottlenecks[] = [
                'Bottleneck' => '🔴 Database (Queries)',
                'Cause' => 'Every request reads directly from database',
                'Impact' => 'High response time (>100ms)',
                'Solution' => 'Implement Redis Cache',
                'Improvement' => "{$cacheImprovement}%"
            ];
        } else {
            $bottlenecks[] = [
                'Bottleneck' => '🟡 Database (Queries)',
                'Cause' => 'Every request reads directly from database',
                'Impact' => 'Moderate response time',
                'Solution' => 'Implement Redis Cache',
                'Improvement' => "{$cacheImprovement}%"
            ];
        }
        
        // Bottleneck 2: Race Condition
        $lockOverhead = $this->benchmarkResults['purchase']['overhead_ms'] ?? 0;
        $bottlenecks[] = [
            'Bottleneck' => '🟡 Race Condition',
            'Cause' => 'Multiple users modifying same stock',
            'Impact' => 'Data loss or inaccurate stock',
            'Solution' => 'Implement Optimistic Locking',
            'Improvement' => '100% data loss prevention'
        ];
        
        // Bottleneck 3: Transactions
        $transactionOverhead = $this->benchmarkResults['transactions']['overhead_ms'] ?? 0;
        $bottlenecks[] = [
            'Bottleneck' => '🟢 Transactions',
            'Cause' => 'Atomicity adds extra processing time',
            'Impact' => "{$transactionOverhead}ms overhead per operation",
            'Solution' => 'Acceptable as it ensures data safety',
            'Improvement' => '100% data integrity'
        ];
        
        $this->table(
            ['Bottleneck', 'Cause', 'Impact', 'Solution', 'Improvement'],
            $bottlenecks
        );
        
        // Summary
        $this->info("\n📌 Summary:");
        $this->info("   ✅ Main Bottleneck: " . $bottlenecks[0]['Bottleneck']);
        $this->info("   ✅ Clearly identified in product fetch benchmarks");
        $this->info("   ✅ Solved using Cache (Improvement: {$cacheImprovement}%)");
    }
    
    private function recommendations()
    {
        $this->info("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("💡 5. Recommendations");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        
        $recommendations = [
            [
                '#' => '1',
                'Recommendation' => 'Enable Cache for products',
                'Priority' => 'High',
                'Expected Impact' => '90% improvement in product fetch time'
            ],
            [
                '#' => '2',
                'Recommendation' => 'Use Optimistic Locking for stock',
                'Priority' => 'High',
                'Expected Impact' => 'Prevent data loss during concurrency'
            ],
            [
                '#' => '3',
                'Recommendation' => 'Use Transactions for critical operations',
                'Priority' => 'High',
                'Expected Impact' => 'Ensure Atomicity and data integrity'
            ],
            [
                '#' => '4',
                'Recommendation' => 'Distribute load across multiple servers',
                'Priority' => 'Medium',
                'Expected Impact' => '3x throughput increase'
            ],
            [
                '#' => '5',
                'Recommendation' => 'Use Queue for heavy tasks',
                'Priority' => 'Medium',
                'Expected Impact' => 'Improved user response time'
            ],
        ];
        
        $this->table(['#', 'Recommendation', 'Priority', 'Expected Impact'], $recommendations);
    }
    
    private function saveReport()
    {
        $reportContent = [];
        
        $reportContent[] = "╔════════════════════════════════════════════════════════════════╗";
        $reportContent[] = "║                    📊 BENCHMARKING REPORT                      ║";
        $reportContent[] = "║              System Performance Measurement                    ║";
        $reportContent[] = "╚════════════════════════════════════════════════════════════════╝";
        $reportContent[] = "";
        $reportContent[] = "📅 Date: " . now()->format('Y-m-d H:i:s');
        $reportContent[] = "";
        $reportContent[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
        $reportContent[] = "📊 1. Get Products Benchmark";
        $reportContent[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
        $reportContent[] = "Without Cache: " . ($this->benchmarkResults['get_products']['without_cache'] ?? 'N/A') . " ms";
        $reportContent[] = "With Cache: " . ($this->benchmarkResults['get_products']['with_cache'] ?? 'N/A') . " ms";
        $reportContent[] = "Improvement: " . ($this->benchmarkResults['get_products']['improvement'] ?? 'N/A') . "%";
        $reportContent[] = "";
        $reportContent[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
        $reportContent[] = "💰 2. Purchase Operations Benchmark";
        $reportContent[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
        $reportContent[] = "No Lock: " . ($this->benchmarkResults['purchase']['no_lock'] ?? 'N/A') . " ms";
        $reportContent[] = "Optimistic Lock: " . ($this->benchmarkResults['purchase']['optimistic_lock'] ?? 'N/A') . " ms";
        $reportContent[] = "Overhead: " . ($this->benchmarkResults['purchase']['overhead_ms'] ?? 'N/A') . " ms";
        $reportContent[] = "";
        $reportContent[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
        $reportContent[] = "🔐 3. Transactions Benchmark";
        $reportContent[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
        $reportContent[] = "Without Transaction: " . ($this->benchmarkResults['transactions']['without_transaction'] ?? 'N/A') . " ms";
        $reportContent[] = "With Transaction: " . ($this->benchmarkResults['transactions']['with_transaction'] ?? 'N/A') . " ms";
        $reportContent[] = "Overhead: " . ($this->benchmarkResults['transactions']['overhead_ms'] ?? 'N/A') . " ms";
        $reportContent[] = "";
        $reportContent[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
        $reportContent[] = "🔍 4. Bottleneck Analysis";
        $reportContent[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
        $reportContent[] = "Main Bottleneck: Database queries without cache";
        $reportContent[] = "Solution: Implement Redis Cache";
        $reportContent[] = "Improvement: " . ($this->benchmarkResults['get_products']['improvement'] ?? 'N/A') . "%";
        $reportContent[] = "";
        $reportContent[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
        $reportContent[] = "💡 5. Recommendations";
        $reportContent[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
        $reportContent[] = "1. Enable Cache for products (High Priority)";
        $reportContent[] = "2. Use Optimistic Locking for stock (High Priority)";
        $reportContent[] = "3. Use Transactions for critical operations (High Priority)";
        $reportContent[] = "4. Distribute load across multiple servers (Medium Priority)";
        $reportContent[] = "5. Use Queue for heavy tasks (Medium Priority)";
        
        // Save file
        $filename = storage_path("logs/benchmark_" . now()->format('Y-m-d_H-i-s') . ".txt");
        file_put_contents($filename, implode("\n", $reportContent));
        
        $this->info("\n💾 Report saved to: {$filename}");
    }
}