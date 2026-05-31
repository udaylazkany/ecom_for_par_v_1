<?php

namespace App\Services;

use App\Repositories\ProductRepository;

class ProductService
{
    protected $repo;

    public function __construct(ProductRepository $repo)
    {
        $this->repo = $repo;
    }

    public function createProduct(array $data)
    {
        // أي منطق إضافي تريده هنا
        $data['version'] = 1;

        return $this->repo->create($data);
    }
    public function getAllProducts()
    {
        return $this->repo->getAllProducts();
    }
    public function baselineFetch()
    {
        return $this->repo->baselineFetch();
    }
    public function loadBalancedFetchParallel()
{
    return $this->repo->loadBalancedFetchParallel();
}

}
