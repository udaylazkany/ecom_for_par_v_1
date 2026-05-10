<?php

namespace App\Http\Controllers;

use App\Services\ProductService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    protected $service;

    public function __construct(ProductService $service)
    {
        $this->service = $service;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'sku'            => 'required|string|unique:products,sku',
            'description'    => 'nullable|string',
            'price'          => 'required|numeric',
            'stock_quantity' => 'required|integer',
            'is_active'      => 'boolean'
        ]);

        $product = $this->service->createProduct($validated);

        return response()->json([
            'message' => 'Product created successfully',
            'data'    => $product
        ], 201);
    }
       public function getallproduct()
    {
        $data = $this->service->getAllProducts();
        return response()->json($data);
    }
}
