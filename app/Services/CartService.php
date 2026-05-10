<?php

namespace App\Services;

use App\Interfaces\CartRepositoryInterface;
use App\Models\Cart;

class CartService
{
    protected $repo;

    public function __construct(CartRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    public function add($userId, $productId, $quantity)
    {
        // إنشاء السلة تلقائيًا إذا ما كانت موجودة
        $cart = Cart::firstOrCreate(
            ['user_id' => $userId]
        );

        // الآن cart_id جاهز
        return $this->repo->addToCart($cart->id, $productId, $quantity);
    }
}
