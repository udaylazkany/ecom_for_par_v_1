<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CheckoutService;

class CheckoutController extends Controller
{
    protected $service;

    public function __construct(CheckoutService $service)
    {
        $this->service = $service;
    }

    public function checkout(Request $request)
    {
        return $this->service->checkout($request->user_id);
    }
}
