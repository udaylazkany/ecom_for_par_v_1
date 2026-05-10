<?php
namespace App\Interfaces;

interface CartRepositoryInterface
{
    public function addToCart($userId, $productId, $quantity);
}
