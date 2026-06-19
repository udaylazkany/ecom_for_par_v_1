<?php

namespace App\Repositories;

use App\Interfaces\ProductRepositoryInterface;
use App\Models\Product;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Aspects\PerformanceAspect;
use App\LoadBalancing\Server;
use App\LoadBalancing\LoadBalancer;
use App\Jobs\LoadBalancedFetchJob;


class ProductRepository implements ProductRepositoryInterface
{
    public function create(array $data)
    {
        $product = Product::create($data);
        Cache::forget('products_list');
        return $product;
    }

    public function getAllProducts()
    {
        return PerformanceAspect::measure("GetAllProducts", function () {
            return Cache::remember('products_list', 60, function () {
                return Product::all();
            });
        });
    }

    public function baselineFetch()
    {
        return PerformanceAspect::measure("Baseline Fetch 1000 requests (No Load Balancing)", function () {
            for ($i = 0; $i < 1000; $i++) {
                Product::all();
            }
        });
    }

// في App/Repositories/ProductRepository.php

public function loadBalancedFetchParallel()
{
    return PerformanceAspect::measure(
        "Load Balanced Parallel Fetch (1000 requests)",
        function () {
            // إنشاء السيرفرات على منافذ مختلفة
            $servers = [
                new Server("Server-A", "localhost", 8000),
                new Server("Server-B", "localhost", 8001),
                new Server("Server-C", "localhost", 8002),
            ];

            $lb = new LoadBalancer($servers, 'round-robin');
            
            $results = [];
            
            for ($i = 0; $i < 1000; $i++) {
                $server = $lb->pickServer();
                if ($server) {
                    $results[] = [
                        'request' => $i + 1,
                        'server' => $server->getName(),
                        'port' => $server->getPort()
                    ];
                    dispatch(new LoadBalancedFetchJob($server));
                }
            }
            
            // تسجيل التوزيع
            $distribution = [];
            foreach ($results as $result) {
                $serverName = $result['server'];
                if (!isset($distribution[$serverName])) {
                    $distribution[$serverName] = 0;
                }
                $distribution[$serverName]++;
            }
            
            Log::info("Load Balancing Distribution:", $distribution);
            
            return [
                'total_requests' => 1000,
                'distribution' => $distribution,
                'servers_status' => $lb->getServersStatus()
            ];
        }
    );
}
public function purchaseProductNoLock(int $productId, int $quantity): array
{
    return PerformanceAspect::measure("NoLock_Purchase_Product_{$productId}", function () use ($productId, $quantity) {
        // تأخير عشوائي لزيادة فرصة التضارب
        usleep(rand(1000, 5000));

        $product = Product::find($productId);
        if (!$product) {
            return ['success' => false, 'message' => 'Product not found'];
        }

        // 🔥 نخصم مباشرة دون التحقق من المخزون
        $product->stock_quantity = $product->stock_quantity - $quantity;
        $product->save();

        return ['success' => true, 'message' => 'Purchase successful (NO LOCK)'];
    });
}


public function purchaseProductWithDistributedLock(int $productId, int $quantity, int $lockTimeout = 10): array
{
    // مفتاح القفل الموزع
    $lockKey = "product_purchase_{$productId}";

    // ============================================================
    // التعديل 1: تغليف الكود بالكامل بـ AOP (قياس الأداء)
    // ============================================================
    return PerformanceAspect::measure("DistributedLock_Purchase_Product_{$productId}", function () use ($productId, $quantity, $lockTimeout, $lockKey) {

        // ============================================================
        // التعديل 2: استخدام block() بدلاً من get() لتحسين تجربة المستخدم
        // عند فشل الحصول على القفل، ننتظر لمدة 5 ثواني كحد أقصى
        // ============================================================
        $lock = Cache::lock($lockKey, $lockTimeout);
        if (!$lock->block(5)) { // ينتظر حتى 5 ثواني للحصول على القفل
            return ['success' => false, 'message' => 'The product is busy, please try again later.'];
        }

        // --- تم الحصول على القفل بنجاح ---
        try {
            // ============================================================
            // التعديل 3: بدء المعاملة (DB Transaction) لضمان الـ ACID
            // ============================================================
            DB::beginTransaction();

            // ============================================================
            // التعديل 4: استخدام lockForUpdate() لقفل الصف على مستوى قاعدة البيانات
            // هذا يمنع أي تداخل حتى لو انتهى وقت القفل الموزع (طبقة أمان إضافية)
            // ============================================================
            $product = Product::where('id', $productId)->lockForUpdate()->first();
            
            if (!$product) {
                DB::rollBack();
                return ['success' => false, 'message' => 'Product not found'];
            }

            if ($product->stock_quantity < $quantity) {
                DB::rollBack();
                return ['success' => false, 'message' => 'Insufficient stock'];
            }

            // 1. تحديث المخزون
            $product->stock_quantity -= $quantity;
            $product->save();

            // ============================================================
            // التعديل 5: إنشاء سجل الطلب (Order) داخل نفس المعاملة
            // يضمن أنه إما تنجح جميع العمليات أو ترجع (Rollback) كاملة
            // ============================================================
            $order = Order::create([
                'product_id' => $productId,
                'quantity' => $quantity,
                'customer_name' => 'DistributedLock_User', // يمكنك جعلها ديناميكية لاحقاً
                'total_price' => ($product->price ?? 100) * $quantity,
                'status' => 'completed',
                'purchased_at' => now()
            ]);

            // إتمام المعاملة بنجاح
            DB::commit();

            // إبطال الكاش بعد التعديل (لتحديث قائمة المنتجات)
            Cache::forget('products_list');

            return [
                'success' => true,
                'message' => 'Purchase successful (DISTRIBUTED LOCK)',
                'order_id' => $order->id,  // إرجاع رقم الطلب للتتبع
                'final_stock' => $product->stock_quantity
            ];

        } catch (\Exception $e) {
            // في حال حدوث أي خطأ، نرجع (Rollback) المعاملة
            DB::rollBack();
            return ['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()];
        } finally {
            // ============================================================
            // التعديل 6: تحرير القفل الموزع في النهاية مهما كانت النتيجة
            // ============================================================
            $lock->release();
        }
    }); // نهاية PerformanceAspect::measure
}
// ==========================================
// شراء مع قفل متفائل (Optimistic Lock)
// ==========================================
public function purchaseProductWithOptimisticLock($productId, $quantity)
{
    $maxRetries = 3;
    $attempts = 0;
    
    while ($attempts < $maxRetries) {
        $product = Product::find($productId);
        if (!$product) {
            return ['success' => false, 'message' => 'Product not found'];
        }
        
        if ($product->stock_quantity < $quantity) {
            return ['success' => false, 'message' => 'Insufficient stock'];
        }
        
        $oldVersion = $product->version ?? 0;
        $updated = Product::where('id', $productId)
            ->where('version', $oldVersion)
            ->update([
                'stock_quantity' => $product->stock_quantity - $quantity,
                'version' => $oldVersion + 1
            ]);
        
        if ($updated > 0) {
            $order = Order::create([
                'product_id' => $productId,
                'quantity' => $quantity,
                'customer_name' => 'Optimistic_User',
                'total_price' => ($product->price ?? 100) * $quantity,
                'status' => 'completed',
                'purchased_at' => now()
            ]);
            
            return [
                'success' => true,
                'message' => 'Purchase successful (OPTIMISTIC LOCK)',
                'order_id' => $order->id,
                'final_stock' => $product->stock_quantity - $quantity
            ];
        }
        
        $attempts++;
    }
    
    return ['success' => false, 'message' => 'Concurrent modification detected. Please retry.'];
}
    public function resetProductForTest(int $productId, int $initialStock, int $initialVersion = 0): void
    {
        Product::where('id', $productId)->update([
            'stock_quantity' => $initialStock,
            'version' => $initialVersion
        ]);
        Cache::forget('products_list');
    }

    public function runConcurrentPurchases(int $productId, int $quantityPerPurchase, int $totalPurchases = 100, string $lockType = 'no_lock'): array
    {
        $label = ($lockType == 'no_lock') ? 'Scenario_NoLock_100_Requests' : 'Scenario_OptimisticLock_100_Requests';
        
        return PerformanceAspect::measure($label, function () use ($productId, $quantityPerPurchase, $totalPurchases, $lockType) {
            $results = [
                'success_count' => 0,
                'fail_count' => 0,
                'errors' => [],
                'start_time' => microtime(true),
                'initial_stock' => null,
                'final_stock' => null,
                'expected_stock' => null,
                'data_integrity' => null,
                'duration_seconds' => null
            ];
            
            $product = Product::find($productId);
            $initialStock = $product->stock_quantity;
            $results['initial_stock'] = $initialStock;
            $results['expected_stock'] = $initialStock - ($quantityPerPurchase * $totalPurchases);
            
            for ($i = 1; $i <= $totalPurchases; $i++) {
                if ($lockType === 'optimistic') {
                    $result = $this->purchaseProductWithOptimisticLock($productId, $quantityPerPurchase);
                } else {
                    $result = $this->purchaseProductNoLock($productId, $quantityPerPurchase);
                }
                if ($result['success']) {
                    $results['success_count']++;
                } else {
                    $results['fail_count']++;
                    $results['errors'][] = "Purchase $i: " . $result['message'];
                }
            }
            
            $finalProduct = Product::find($productId);
            $results['final_stock'] = $finalProduct->stock_quantity;
            $results['end_time'] = microtime(true);
            $results['duration_seconds'] = round($results['end_time'] - $results['start_time'], 2);
            
            if ($results['final_stock'] == $results['expected_stock']) {
                $results['data_integrity'] = '✅ PASS - No data corruption';
            } else {
                $results['data_integrity'] = "❌ FAIL - Expected {$results['expected_stock']}, Got {$results['final_stock']}";
            }
            return $results;
        });
    }

    // ==================== الطلب 8: Transaction Integrity ====================

    public function purchaseWithoutTransaction(int $productId, int $quantity, array $orderData): array
    {
        return PerformanceAspect::measure("Purchase_Without_Transaction", function () use ($productId, $quantity, $orderData) {
            $product = Product::find($productId);
            if (!$product) {
                return ['success' => false, 'message' => 'Product not found'];
            }
            if ($product->stock_quantity < $quantity) {
                return ['success' => false, 'message' => 'Insufficient stock'];
            }
            
            $product->stock_quantity = $product->stock_quantity - $quantity;
            $product->save();
            
            if (isset($orderData['simulate_failure']) && $orderData['simulate_failure'] === true) {
                throw new \Exception('Simulated failure after stock update!');
            }
            
            $order = Order::create([
                'product_id' => $productId,
                'quantity' => $quantity,
                'customer_name' => $orderData['customer_name'] ?? 'Test User',
                'total_price' => ($product->price ?? 100) * $quantity,
                'status' => 'completed'
            ]);
            
            Cache::forget('products_list');
            return ['success' => true, 'message' => 'Purchase completed (NO TRANSACTION)', 'final_stock' => $product->stock_quantity, 'order_id' => $order->id];
        });
    }

    public function purchaseWithTransaction(int $productId, int $quantity, array $orderData): array
    {
        return PerformanceAspect::measure("Purchase_With_Transaction", function () use ($productId, $quantity, $orderData) {
            return DB::transaction(function () use ($productId, $quantity, $orderData) {
                $product = Product::where('id', $productId)->lockForUpdate()->first();
                if (!$product) {
                    return ['success' => false, 'message' => 'Product not found'];
                }
                if ($product->stock_quantity < $quantity) {
                    return ['success' => false, 'message' => 'Insufficient stock'];
                }
                
                $product->stock_quantity = $product->stock_quantity - $quantity;
                $product->save();
                
                if (isset($orderData['simulate_failure']) && $orderData['simulate_failure'] === true) {
                    throw new \Exception('Simulated failure - transaction will rollback!');
                }
                
                $order = Order::create([
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'customer_name' => $orderData['customer_name'] ?? 'Test User',
                    'total_price' => ($product->price ?? 100) * $quantity,
                    'status' => 'completed'
                ]);
                
                Cache::forget('products_list');
                return ['success' => true, 'message' => 'Purchase completed (WITH TRANSACTION)', 'final_stock' => $product->stock_quantity, 'order_id' => $order->id];
            });
        });
    }

    public function testTransactionIntegrity(int $productId, int $quantity, array $orderData, string $scenario = 'compare'): array
    {
        $results = [];
        
        if ($scenario == 'without_transaction' || $scenario == 'compare') {
            $this->resetProductForTest($productId, 100, 0);
            Order::where('customer_name', $orderData['customer_name'] ?? 'Test User')->delete();
            $ordersBefore = Order::count();
            $stockBefore = Product::find($productId)->stock_quantity;
            $startTime = microtime(true);
            try {
                $result = $this->purchaseWithoutTransaction($productId, $quantity, $orderData);
            } catch (\Exception $e) {
                $result = ['success' => false, 'error' => $e->getMessage()];
            }
            $endTime = microtime(true);
            $ordersAfter = Order::count();
            $stockAfter = Product::find($productId)->stock_quantity;
            $results['without_transaction'] = [
                'success' => $result['success'] ?? false,
                'message' => $result['message'] ?? $result['error'] ?? 'Unknown',
                'time_ms' => round(($endTime - $startTime) * 1000, 2),
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'orders_before' => $ordersBefore,
                'orders_after' => $ordersAfter,
                'analysis' => $this->analyzeTransactionResult($stockBefore, $stockAfter, $ordersBefore, $ordersAfter, $quantity, $result['success'] ?? false)
            ];
        }
        
        if ($scenario == 'with_transaction' || $scenario == 'compare') {
            $this->resetProductForTest($productId, 100, 0);
            Order::where('customer_name', $orderData['customer_name'] ?? 'Test User')->delete();
            $ordersBefore = Order::count();
            $stockBefore = Product::find($productId)->stock_quantity;
            $startTime = microtime(true);
            try {
                $result = $this->purchaseWithTransaction($productId, $quantity, $orderData);
            } catch (\Exception $e) {
                $result = ['success' => false, 'error' => $e->getMessage()];
            }
            $endTime = microtime(true);
            $ordersAfter = Order::count();
            $stockAfter = Product::find($productId)->stock_quantity;
            $results['with_transaction'] = [
                'success' => $result['success'] ?? false,
                'message' => $result['message'] ?? $result['error'] ?? 'Unknown',
                'time_ms' => round(($endTime - $startTime) * 1000, 2),
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'orders_before' => $ordersBefore,
                'orders_after' => $ordersAfter,
                'analysis' => $this->analyzeTransactionResult($stockBefore, $stockAfter, $ordersBefore, $ordersAfter, $quantity, $result['success'] ?? false)
            ];
        }
        return $results;
    }

    private function analyzeTransactionResult($stockBefore, $stockAfter, $ordersBefore, $ordersAfter, $quantity, $success): string
    {
        if ($success) {
            if ($stockAfter == $stockBefore - $quantity && $ordersAfter == $ordersBefore + 1) {
                return "✅ PASS - Stock decreased correctly and order created";
            } else {
                return "❌ FAIL - Stock: {$stockBefore}→{$stockAfter}, Orders: {$ordersBefore}→{$ordersAfter}";
            }
        } else {
            if ($stockAfter == $stockBefore && $ordersAfter == $ordersBefore) {
                return "✅ PASS - On failure, nothing changed (Atomic rollback)";
            } else {
                return "❌ FAIL - On failure, stock changed or order created (Not atomic)";
            }
        }
    }
    // في App/Repositories/ProductRepository.php

// ==================== دوال اختبار الضغط ====================

// 1️⃣ بدون أي تحسينات (سينهار)
public function purchaseForStressTestNoOptimization(int $productId, int $quantity): array
{
    return PerformanceAspect::measure("StressTest_NoOptimization", function () use ($productId, $quantity) {
        // تأخير عشوائي يسبب تباطؤ
        usleep(rand(10000, 50000));
        
        $product = Product::find($productId);
        
        if (!$product) {
            return ['success' => false, 'message' => 'Product not found'];
        }
        
        if ($product->stock_quantity >= $quantity) {
            $product->stock_quantity = $product->stock_quantity - $quantity;
            $product->save();
            return ['success' => true, 'message' => 'Purchase successful (NO OPTIMIZATION)'];
        }
        
        return ['success' => false, 'message' => 'Insufficient stock'];
    });
}

// 2️⃣ مع التحسينات (Caching + Optimistic Lock)
public function purchaseForStressTestWithOptimization(int $productId, int $quantity): array
{
    return PerformanceAspect::measure("StressTest_WithOptimization", function () use ($productId, $quantity) {
        
        return \DB::transaction(function () use ($productId, $quantity) {
            $product = Product::where('id', $productId)->lockForUpdate()->first();
            
            if (!$product) {
                return ['success' => false, 'message' => 'Product not found'];
            }
            
            if ($product->stock_quantity < $quantity) {
                return ['success' => false, 'message' => 'Insufficient stock'];
            }
            
            $currentVersion = $product->version;
            $newStock = $product->stock_quantity - $quantity;
            
            $updated = Product::where('id', $productId)
                ->where('version', $currentVersion)
                ->update([
                    'stock_quantity' => $newStock,
                    'version' => \DB::raw('version + 1')
                ]);
            
            if ($updated) {
                // محاكاة إنشاء طلب
                Order::create([
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'customer_name' => 'StressTest_User',
                    'total_price' => ($product->price ?? 100) * $quantity,
                    'status' => 'completed'
                ]);
                
                Cache::forget('products_list');
                return ['success' => true, 'message' => 'Purchase successful (WITH OPTIMIZATION)'];
            }
            
            return ['success' => false, 'message' => 'Concurrent conflict, please retry'];
        });
    });
}
}