<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Repositories\ProductRepository;
use App\Models\Product;
use App\LoadBalancing\Server;
use App\LoadBalancing\LoadBalancer;
use Illuminate\Support\Facades\Log;

class TestDistributedLockServers extends Command
{
    protected $signature = 'test:distributed-lock-servers {requests?} {strategy?}';
    protected $description = 'Test distributed lock with requests coming from 3 different servers';

    public function handle(ProductRepository $repo)
    {
        $totalRequests = $this->argument('requests') ?? 100;
        $strategy = $this->argument('strategy') ?? 'round-robin';

        $this->newLine();
        $this->info('🧪 Distributed Lock Test Across 3 Servers');
        $this->line(str_repeat('═', 60));
        $this->line("📊 Requests: {$totalRequests}");
        $this->line("🎯 Distribution Strategy: {$strategy}");
        $this->line(str_repeat('═', 60));

        $servers = [
            new Server('Server-A', '192.168.1.10', 8000),
            new Server('Server-B', '192.168.1.11', 8001),
            new Server('Server-C', '192.168.1.12', 8002),
        ];

        $productId = 1;
        $quantity = 1;
        $initialStock = 100;
        $expectedStock = max(0, $initialStock - ($totalRequests * $quantity));

        // ==========================================
        // Scenario 1: No Lock
        // ==========================================
        $this->warn("\n🔴 Scenario 1: Purchase Without Lock (No Lock)");
        $repo->resetProductForTest($productId, $initialStock, 0);

        $lb = new LoadBalancer($servers, $strategy);
        $distributionNoLock = ['Server-A' => 0, 'Server-B' => 0, 'Server-C' => 0];
        $processes = [];

        for ($i = 0; $i < $totalRequests; $i++) {
            $server = $lb->pickServer();
            if (!$server) {
                $server = $servers[array_rand($servers)];
            }
            $distributionNoLock[$server->getName()]++;
            
            // Run each request as a separate process
            $cmd = "php artisan purchase:single {$productId} {$quantity} > /dev/null 2>&1 &";
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $cmd = "start /B php artisan purchase:single {$productId} {$quantity} > nul 2>&1";
            }
            exec($cmd);
        }

        // Wait for processes to complete (depends on system speed)
        sleep(5); // Increased for reliability

        $productNoLock = Product::find($productId);
        $finalNoLock = $productNoLock->stock_quantity;
        $durationNoLock = 'N/A (async)';

        $this->line("   📦 Actual Stock: {$finalNoLock} | Expected: {$expectedStock}");
        $this->line("   📊 Request Distribution: " . json_encode($distributionNoLock));

        if ($finalNoLock === $expectedStock) {
            $this->error('   ❌ Test failed (no race condition detected) - maybe request count is low');
        } else {
            $this->error('   ❌ Race Condition! Sold more than stock.');
        }
////////////////////
        // ==========================================
        // Scenario 2: Distributed Lock
        // ==========================================
        $this->warn("\n🟢 Scenario 2: Purchase With Distributed Lock");
        $repo->resetProductForTest($productId, $initialStock, 0);

        $lb = new LoadBalancer($servers, $strategy);
        $distributionLock = ['Server-A' => 0, 'Server-B' => 0, 'Server-C' => 0];

        for ($i = 0; $i < $totalRequests; $i++) {
            $server = $lb->pickServer();
            if (!$server) {
                $server = $servers[array_rand($servers)];
            }
            $distributionLock[$server->getName()]++;
            
            // Run each request with lock as a separate process
            $cmd = "php artisan purchase:single {$productId} {$quantity} lock > /dev/null 2>&1 &";
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $cmd = "start /B php artisan purchase:single {$productId} {$quantity} lock > nul 2>&1";
            }
            exec($cmd);
        }

        sleep(5); // Increased for reliability

        $productLock = Product::find($productId);
        $finalLock = $productLock->stock_quantity;

        $this->line("   📦 Actual Stock: {$finalLock} | Expected: {$expectedStock}");
        $this->line("   📊 Request Distribution: " . json_encode($distributionLock));

        if ($finalLock === $expectedStock) {
            $this->info('   ✅ Success! Distributed lock prevented race condition even with requests from different servers.');
        } else {
            $this->error('   ❌ Failed! Check Cache Driver settings (database).');
        }

        // ==========================================
        // Final Comparison Table
        // ==========================================
        $this->newLine();
        $this->line(str_repeat('═', 60));
        $this->info('📋 Final Summary with Server Distribution:');
        $this->table(
            ['Scenario', 'Final Stock', 'Expected', 'Distribution', 'Result'],
            [
                [
                    'No Lock',
                    $finalNoLock,
                    $expectedStock,
                    $this->formatDistribution($distributionNoLock),
                    ($finalNoLock === $expectedStock) ? '⚠️ Unexpected' : '❌ Failed (Race Condition)'
                ],
                [
                    'Distributed Lock',
                    $finalLock,
                    $expectedStock,
                    $this->formatDistribution($distributionLock),
                    ($finalLock === $expectedStock) ? '✅ Success' : '❌ Failed'
                ],
            ]
        );
//////////////////
        $this->line(str_repeat('═', 60));
        $this->info('🏁 Test finished.');

        return 0;
    }

    private function formatDistribution(array $dist): string
    {
        $parts = [];
        foreach ($dist as $server => $count) {
            $parts[] = "{$server}: {$count}";
        }
        return implode(' | ', $parts);
    }
}