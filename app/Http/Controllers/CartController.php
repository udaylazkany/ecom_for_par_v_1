<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CartService;

class CartController extends Controller
{
      protected $service;

    public function __construct(CartService $service)
    {
        $this->service = $service;
    }

    public function add(Request $request)
{
    return $this->service->add(
        $request->user_id,
        $request->product_id,
        $request->quantity
    );
}

}
