<?php

namespace App\Repositories;

use App\Interfaces\CheckoutRepositoryInterface;
use App\Models\Cart;
use App\Models\Product;
use App\Models\Order;
use App\Models\order_item;
use Illuminate\Support\Facades\DB;
use App\Aspects\PerformanceAspect;
use App\Jobs\SendInvoiceJob;

class CheckoutRepository implements CheckoutRepositoryInterface
{
    public function checkout($userId)
    {
        return PerformanceAspect::measure("Checkout", function () use ($userId) {

            return DB::transaction(function () use ($userId) {

                // 1) جلب السلة
                $cart = Cart::where('user_id', $userId)->firstOrFail();
                $items = $cart->items;

                if ($items->isEmpty()) {
                   return response()->json([
    'error' => 'السلة فارغة'
], 400);

                }

                // 2) إنشاء الطلب
                $order = Order::create([
                    'user_id' => $userId,
                    'total_amount' => 0,
                ]);

                $total = 0;

                // 3) معالجة كل عنصر في السلة
                foreach ($items as $item) {

                    // 3.1) قفل المنتج لمنع الـ Race Condition
                    $product = Product::where('id', $item->product_id)
                        ->lockForUpdate()
                        ->first();

                    if (!$product) {
                        throw new \Exception("المنتج غير موجود");
                    }

                    // 3.2) التحقق من الكمية
                    if ($product->stock_quantity < $item->quantity) {
                        throw new \Exception("الكمية غير متوفرة للمنتج: {$product->name}");
                    }

                    // 3.3) تنزيل المخزون
                    $product->stock_quantity -= $item->quantity;
                    $product->save();

                    // 3.4) إنشاء عنصر الطلب
                    order_item::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'quantity' => $item->quantity,
                        'unit_price' => $product->price,
                        'subtotal'   => $product->price * $item->quantity,
                    ]);

                    $total += $product->price * $item->quantity;
                }

                // 4) تحديث إجمالي الطلب
                $order->update(['total_amount' => $total]);
 
                SendInvoiceJob::dispatch($order->id);
 
                // 5) تفريغ السلة
                $cart->items()->delete();

                return $order;
            });
        });
    }
}
