<?php

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use App\Aspects\PerformanceAspect;


class ProductRepository
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

}
