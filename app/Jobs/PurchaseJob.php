<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Repositories\ProductRepository;
use Illuminate\Support\Facades\Log;

class PurchaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * معرف المنتج
     */
    protected int $productId;

    /**
     * الكمية المراد شراؤها
     */
    protected int $quantity;

    /**
     * نوع القفل المستخدم (no_lock, optimistic, distributed)
     */
    protected string $lockType;

    /**
     * اسم السيرفر الذي أرسل الطلب (اختياري، للتتبع)
     */
    protected ?string $serverName;

    /**
     * عدد محاولات إعادة المحاولة في حال فشل القفل المتفائل
     */
    protected int $maxRetries;

    /**
     * Create a new job instance.
     */
    public function __construct(
        int $productId = 1,
        int $quantity = 1,
        string $lockType = 'no_lock',
        ?string $serverName = null,
        int $maxRetries = 3
    ) {
        $this->productId = $productId;
        $this->quantity = $quantity;
        $this->lockType = $lockType;
        $this->serverName = $serverName;
        $this->maxRetries = $maxRetries;
    }

    /**
     * Execute the job.
     */
    public function handle(ProductRepository $repo): void
    {
        // تسجيل بداية المعالجة مع معلومات السيرفر
        Log::info("📦 PurchaseJob started", [
            'product_id' => $this->productId,
            'quantity' => $this->quantity,
            'lock_type' => $this->lockType,
            'server' => $this->serverName ?? 'Unknown',
            'job_id' => $this->job?->getJobId(),
        ]);

        try {
            // تنفيذ عملية الشراء حسب نوع القفل المطلوب
            $result = match ($this->lockType) {
                'no_lock' => $repo->purchaseProductNoLock($this->productId, $this->quantity),
                'optimistic' => $repo->purchaseProductWithOptimisticLock(
                    $this->productId,
                    $this->quantity,
                    $this->maxRetries
                ),
                'distributed' => $repo->purchaseProductWithDistributedLock(
                    $this->productId,
                    $this->quantity
                ),
                default => $repo->purchaseProductNoLock($this->productId, $this->quantity),
            };

            // تسجيل نتيجة المعالجة
            Log::info("✅ PurchaseJob completed", [
                'product_id' => $this->productId,
                'success' => $result['success'] ?? false,
                'message' => $result['message'] ?? 'Unknown',
                'server' => $this->serverName ?? 'Unknown',
            ]);

        } catch (\Exception $e) {
            // تسجيل أي خطأ حدث أثناء المعالجة
            Log::error("❌ PurchaseJob failed", [
                'product_id' => $this->productId,
                'error' => $e->getMessage(),
                'server' => $this->serverName ?? 'Unknown',
                'trace' => $e->getTraceAsString(),
            ]);

            // إعادة محاولة الجوب في حال فشل (Laravel سيعيد المحاولة تلقائياً حسب إعدادات retry)
            throw $e;
        }
    }

    /**
     * الحصول على اسم السيرفر الذي أرسل الطلب
     */
    public function getServerName(): ?string
    {
        return $this->serverName;
    }

    /**
     * الحصول على نوع القفل المستخدم
     */
    public function getLockType(): string
    {
        return $this->lockType;
    }
}