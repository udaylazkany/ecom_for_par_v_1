<?php
namespace App\Repositories;

use App\Interfaces\CartRepositoryInterface;
use App\Aspects\PerformanceAspect;
use App\Models\cart_item;
use Illuminate\Support\Facades\DB;

class CartRepository implements CartRepositoryInterface
{
   public function addToCart($cartId, $productId, $quantity)
{
    return PerformanceAspect::measure("AddToCart", function () use ($cartId, $productId, $quantity) {

        return DB::transaction(function () use ($cartId, $productId, $quantity) {

            $cartItem = cart_item::where('cart_id', $cartId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            if ($cartItem) {
                $cartItem->quantity += $quantity;
                $cartItem->save();
            } else {
                $cartItem = cart_item::create([
                    'cart_id' => $cartId,
                    'product_id' => $productId,
                    'quantity' => $quantity,
                ]);
            }

            return $cartItem;
        });
    });
}
 }

