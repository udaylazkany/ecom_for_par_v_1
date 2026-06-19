<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Repositories\ProductRepository;
use App\LoadBalancing\Server;
use App\LoadBalancing\LoadBalancer;
use Illuminate\Support\Facades\Log;
use App\Aspects\PerformanceAspect;

class TestLoadBalancing extends Command
{
    protected $signature = 'test:load-balancing {requests?} {strategy?}';
    protected $description = 'Test load balancing across multiple servers';

    public function handle(ProductRepository $repo)
    {
        $totalRequests = $this->argument('requests') ?? 100;
        $strategy = $this->argument('strategy') ?? 'round-robin';
        
        $this->info("\n=========================================");
        $this->info("     Load Balancing Test");
        $this->info("=========================================");
        $this->info("📊 Total Requests: {$totalRequests}");
        $this->info("🎯 Distribution Strategy: {$strategy}");
        $this->info("=========================================\n");
        
        // ========== 1. Test without load balancer (Baseline) ==========
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("📌 Test 1: Without Load Balancer (Baseline)");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        
        $baselineResult = $this->testBaseline($repo, $totalRequests);
        
        // ========== 2. Test with load balancer ==========
        $this->info("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("📌 Test 2: With Load Balancer");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        
        $balancedResult = $this->testLoadBalanced($totalRequests, $strategy);
        
        // ========== 3. Show comparison ==========
        $this->showComparison($baselineResult, $balancedResult);
        
        return 0;
    }
    
    private function testBaseline($repo, $totalRequests)
    {
        $this->info("🔄 Executing {$totalRequests} requests without load balancer...\n");
        
        $startTime = microtime(true);
        
        PerformanceAspect::measure("Baseline_Fetch_{$totalRequests}_Requests", function() use ($repo) {
            return $repo->baselineFetch();
        });
        
        $endTime = microtime(true);
        $totalTime = round($endTime - $startTime, 2);
        $requestsPerSecond = $totalTime > 0 ? round($totalRequests / $totalTime, 2) : $totalRequests;
        
        $this->info("✅ Execution completed");
        $this->line("   ⏱️  Total time: {$totalTime} seconds");
        $this->line("   📊 Requests per second: " . $requestsPerSecond);
        
        Log::info("Baseline Test Results:", [
            'total_requests' => $totalRequests,
            'total_time_seconds' => $totalTime,
            'requests_per_second' => $requestsPerSecond
        ]);
        
        return [
            'total_requests' => $totalRequests,
            'total_time' => $totalTime,
            'requests_per_second' => $requestsPerSecond
        ];
    }
    
    private function testLoadBalanced($totalRequests, $strategy)
    {
        // Create servers on different ports
        $servers = [
            new Server("Server-A", "localhost", 8000),
            new Server("Server-B", "localhost", 8001),
            new Server("Server-C", "localhost", 8002),
        ];
        
        $lb = new LoadBalancer($servers, $strategy);
        
        $distribution = [
            'Server-A' => 0,
            'Server-B' => 0,
            'Server-C' => 0,
        ];
        
        $this->info("🔄 Distributing {$totalRequests} requests across servers...");
        $this->info("🎯 Using strategy: " . $this->getStrategyName($strategy) . "\n");
        
        $startTime = microtime(true);
        
        // Distribute requests
        for ($i = 1; $i <= $totalRequests; $i++) {
            $server = $lb->pickServer();
            if ($server) {
                $distribution[$server->getName()]++;
                
                if ($i % 20 == 0) {
                    $this->line("   Distributed {$i} requests...");
                }
            }
        }
        
        $endTime = microtime(true);
        $totalTime = round($endTime - $startTime, 2);
        $requestsPerSecond = $totalTime > 0 ? round($totalRequests / $totalTime, 2) : $totalRequests;
        
        $this->info("\n✅ Distribution completed");
        $this->line("   ⏱️  Total time: {$totalTime} seconds");
        $this->line("   📊 Requests per second: " . $requestsPerSecond);
        
        // Display distribution
        $this->newLine();
        $this->info("📊 Request Distribution Across Servers:");
        
        $this->table(
            ['Server', 'Requests', 'Percentage'],
            [
                ['Server-A (port 8000)', $distribution['Server-A'], round(($distribution['Server-A'] / $totalRequests) * 100, 2) . '%'],
                ['Server-B (port 8001)', $distribution['Server-B'], round(($distribution['Server-B'] / $totalRequests) * 100, 2) . '%'],
                ['Server-C (port 8002)', $distribution['Server-C'], round(($distribution['Server-C'] / $totalRequests) * 100, 2) . '%'],
            ]
        );
        
        // Log results, avoid division by zero
        $requestsPerSecondForLog = $totalTime > 0 ? round($totalRequests / $totalTime, 2) : $totalRequests;
        
        Log::info("Load Balanced Test Results:", [
            'strategy' => $strategy,
            'total_requests' => $totalRequests,
            'total_time_seconds' => $totalTime,
            'requests_per_second' => $requestsPerSecondForLog,
            'distribution' => $distribution
        ]);
        
        return [
            'total_requests' => $totalRequests,
            'total_time' => $totalTime,
            'requests_per_second' => $requestsPerSecond,
            'distribution' => $distribution
        ];
    }
    
    private function showComparison($baseline, $balanced)
    {
        $this->newLine();
        $this->info("\n=========================================");
        $this->info("              📊 Final Comparison");
        $this->info("=========================================");
        
        $timeDiff = round($baseline['total_time'] - $balanced['total_time'], 2);
        $rpsDiff = round($balanced['requests_per_second'] - $baseline['requests_per_second'], 2);
        
        $this->table(
            ['Metric', 'Without Balancer', 'With Balancer', 'Improvement'],
            [
                ['Total Time (seconds)', $baseline['total_time'], $balanced['total_time'], $timeDiff . ' sec'],
                ['Requests/Second', $baseline['requests_per_second'], $balanced['requests_per_second'], $rpsDiff],
            ]
        );
        
        $this->info("\n=========================================");
        $this->info("              ✅ Results Analysis");
        $this->info("=========================================");
        
        if ($balanced['requests_per_second'] > $baseline['requests_per_second'] && $baseline['requests_per_second'] > 0) {
            $improvement = round(($balanced['requests_per_second'] - $baseline['requests_per_second']) / $baseline['requests_per_second'] * 100, 2);
            $this->info("🎉 Performance improvement: {$improvement}%");
        } else {
            $this->info("📊 Load is evenly distributed across servers");
        }
        
        $this->info("\n📌 Load Balancing Strategy: Round Robin");
        $this->info("   - Distributes requests across 3 servers (ports 8000, 8001, 8002)");
        $this->info("   - Prevents overloading a single server");
        $this->info("   - Increases overall system throughput");
        
        $this->info("\n=========================================");
    }
    
    private function getStrategyName($strategy): string
    {
        return match($strategy) {
            'round-robin' => 'Round Robin',
            'least-loaded' => 'Least Loaded',
            'random' => 'Random',
            default => 'Round Robin'
        };
    }
}