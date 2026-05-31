<?php

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use App\Aspects\PerformanceAspect;
use App\LoadBalancing\Server;
use App\LoadBalancing\LoadBalancer;
use App\Jobs\LoadBalancedFetchJob;
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
public function baselineFetch()
{
    return PerformanceAspect::measure("Baseline Fetch 1000 requests (No Load Balancing)", function () {

        for ($i = 0; $i < 1000; $i++) {
            Product::all(); // بدون return
        }

    });
}
public function loadBalancedFetchParallel()
{
    return PerformanceAspect::measure(
        "Load Balanced Parallel Fetch (1000 requests)",
        function () {

            $servers = [
                new Server("A"),
                new Server("B"),
                new Server("C"),
            ];

            $lb = new LoadBalancer($servers);

            for ($i = 0; $i < 1000; $i++) {
                $serverName = $lb->pickServer();
                dispatch(new LoadBalancedFetchJob($serverName));
            }
        }
    );
}


}
