<?php

namespace App\LoadBalancing;

use App\Models\product;

class Server
{
    public string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function fetchAllProducts()
    {
        // محاكاة استعلام DB
        return product::all();
    }
}
