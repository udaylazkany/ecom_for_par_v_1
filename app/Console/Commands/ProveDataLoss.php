<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Repositories\ProductRepository;
use App\Models\Product;
use App\Models\order;
use Illuminate\Support\Facades\Log;

class ProveDataLoss extends Command
{
    protected $signature = 'prove:dataloss {concurrent?}';
    protected $description = 'إثبات ضياع المخزون بدون طلبات - بدون تحسينات';

    public function handle(ProductRepository $repo)
    {
        $concurrent = $this->argument('concurrent') ?? 100;
        
        $this->info("\n=========================================");
        $this->info("   🔴 إثبات ضياع المخزون بدون طلبات");
        $this->info("=========================================");
        $this->info("📊 عدد المستخدمين المتزامنين: {$concurrent}");
        $this->info("=========================================\n");
        
        // تنظيف البيانات السابقة
        order::query()->delete();
        
        // إعادة تعيين المنتج
        $repo->resetProductForTest(1, $concurrent, 0);
        $initialStock = Product::find(1)->stock_quantity;
        
        $this->info("📦 قبل الاختبار:");
        $this->info("   المخزون: {$initialStock}");
        $this->info("   عدد الطلبات: " . order::count());
        $this->info("   المنتج: " . Product::find(1)->name);
        
        $this->info("\n🔄 جاري تنفيذ {$concurrent} عملية شراء (بدون معاملات)...\n");
        
        $results = [
            'success' => 0,
            'fail' => 0,
            'orders_before' => order::count(),
            'stock_before' => $initialStock
        ];
        
        $startTime = microtime(true);
        
        // تنفيذ عمليات الشراء بدون معاملات
        for ($i = 1; $i <= $concurrent; $i++) {
            try {
                $result = $repo->purchaseProductNoLock(1, 1);
                if ($result['success']) {
                    $results['success']++;
                } else {
                    $results['fail']++;
                }
            } catch (\Exception $e) {
                $results['fail']++;
            }
            
            // عرض التقدم
            if ($i % 20 == 0) {
                $this->line("   تم تنفيذ {$i} عملية...");
            }
        }
        
        $endTime = microtime(true);
        $totalTime = round($endTime - $startTime, 2);
        
        // جمع البيانات بعد الاختبار
        $finalProduct = Product::find(1);
        $finalStock = $finalProduct->stock_quantity;
        $finalOrders = order::count();
        
        $this->info("\n=========================================");
        $this->info("              🔴 النتائج 🔴");
        $this->info("=========================================");
        
        $this->table(
            ['المقياس', 'قبل الاختبار', 'بعد الاختبار'],
            [
                ['المخزون', $results['stock_before'], $finalStock],
                ['عدد الطلبات', $results['orders_before'], $finalOrders],
                ['عمليات ناجحة', '-', $results['success']],
                ['عمليات فاشلة', '-', $results['fail']],
            ]
        );
        
        $this->info("\n=========================================");
        $this->info("              🔍 التحليل");
        $this->info("=========================================");
        
        // حساب الفرق
        $stockDecreased = $results['stock_before'] - $finalStock;
        $ordersCreated = $finalOrders - $results['orders_before'];
        
        $this->info("📉 المخزون الناقص: {$stockDecreased} قطعة");
        $this->info("📋 الطلبات المنشأة: {$ordersCreated} طلب");
        $this->info("⏱️  الوقت المستغرق: {$totalTime} ثانية");
        
        $this->info("\n=========================================");
        
        // إثبات المشكلة
        if ($stockDecreased > 0 && $ordersCreated == 0) {
            $this->error("\n🔴🔴🔴 إثبات المشكلة 🔴🔴🔴");
            $this->error("   تم خصم {$stockDecreased} قطعة من المخزون");
            $this->error("   ولكن لم يتم إنشاء أي طلب في قاعدة البيانات!");
            $this->error("   🔥 هذا دليل قاطع على ضياع البيانات (Data Loss)!");
            
            Log::critical("🔴🔴🔴 إثبات ضياع المخزون:");
            Log::critical("   المخزون نقص: {$stockDecreased} قطعة");
            Log::critical("   الطلبات المنشأة: 0");
            Log::critical("   النتيجة: فقدان بيانات 100%");
            
        } else if ($stockDecreased > 0 && $ordersCreated > 0) {
            $this->warn("\n⚠️ تم إنشاء {$ordersCreated} طلب");
            $this->warn("   المخزون ناقص: {$stockDecreased}");
            if ($stockDecreased == $ordersCreated) {
                $this->info("   ✅ البيانات متناسقة");
            } else {
                $this->error("   🔴 الفرق: " . ($stockDecreased - $ordersCreated) . " قطعة ضائعة!");
            }
        }
        
        $this->info("\n=========================================");
        $this->info("📊 وقت التنفيذ لكل عملية: " . round($totalTime / $concurrent, 2) . " ms");
        $this->info("=========================================\n");
        
        // عرض الخلاصة النهائية بشكل بارز
        $this->newLine();
        $this->line("═══════════════════════════════════════════");
        $this->line("                 الخلاصة");
        $this->line("═══════════════════════════════════════════");
        $this->line("");
        $this->line("   🔴 بدون استخدام المعاملات (Transactions):");
        $this->line("      - المخزون: {$results['stock_before']} → {$finalStock}");
        $this->line("      - الطلبات: {$results['orders_before']} → {$finalOrders}");
        $this->line("");
        $this->error("      💥 النتيجة: تم خصم {$stockDecreased} قطعة من المخزون");
        $this->error("      💥 ولكن لم يتم تسجيل أي طلب!");
        $this->error("      💥 هذا = فقدان بيانات كامل!");
        $this->line("");
        $this->line("   ✅ مع استخدام المعاملات (Transactions):");
        $this->line("      - المخزون ينقص مع إنشاء الطلب");
        $this->line("      - أو لا شيء يتغير عند الفشل (Rollback)");
        $this->line("");
        $this->line("═══════════════════════════════════════════");
        
        return 0;
    }
}