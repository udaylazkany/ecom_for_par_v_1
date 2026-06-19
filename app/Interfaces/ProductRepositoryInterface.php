<?php

namespace App\Interfaces;

interface ProductRepositoryInterface
{
    public function getAllProducts();
    public function create(array $data);
    public function baselineFetch();
    public function loadBalancedFetchParallel();
    
    // دوال السيناريوهات
    public function purchaseProductNoLock(int $productId, int $quantity): array;
    public function purchaseProductWithDistributedLock(int $productId, int $quantity, int $maxRetries = 3): array;
    public function runConcurrentPurchases(int $productId, int $quantityPerPurchase, int $totalPurchases, string $lockType): array;
    public function resetProductForTest(int $productId, int $initialStock, int $initialVersion): void;
    public function purchaseWithTransaction(int $productId, int $quantity, array $orderData): array;
    public function purchaseWithoutTransaction(int $productId, int $quantity, array $orderData): array;
    public function testTransactionIntegrity(int $productId, int $quantity, array $orderData, string $scenario): array;
    public function purchaseForStressTestNoOptimization(int $productId, int $quantity): array;
    public function purchaseForStressTestWithOptimization(int $productId, int $quantity): array;
    
    }