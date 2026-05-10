<?php

namespace App\Services;

use App\Interfaces\CheckoutRepositoryInterface;

class CheckoutService
{
    protected $repo;

    public function __construct(CheckoutRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    public function checkout($userId)
    {
        return $this->repo->checkout($userId);
    }
}
