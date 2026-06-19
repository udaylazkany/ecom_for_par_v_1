<?php

namespace App\LoadBalancing;

use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Server
{
    public string $name;
    private string $host;
    private int $port;
    private int $load;
    private bool $isAvailable;

    public function __construct(string $name, string $host = 'localhost', int $port = 8000)
    {
        $this->name = $name;
        $this->host = $host;
        $this->port = $port;
        $this->load = 0;
        $this->isAvailable = true;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getUrl(): string
    {
        return "http://{$this->host}:{$this->port}";
    }

    public function getLoad(): int
    {
        return $this->load;
    }

    public function incrementLoad(): void
    {
        $this->load++;
    }

    public function decrementLoad(): void
    {
        if ($this->load > 0) {
            $this->load--;
        }
    }

    public function isAvailable(): bool
    {
        return $this->isAvailable && $this->load < 10000;
    }

    public function setAvailable(bool $available): void
    {
        $this->isAvailable = $available;
    }

    // الدالة القديمة - للاستخدام المحلي (بدون HTTP)
    public function fetchAllProductsLocal()
    {
        return Product::all();
    }

    // الدالة الجديدة - للاتصال بالسيرفر الخارجي
    public function fetchAllProductsRemote(): array
    {
        $startTime = microtime(true);
        
        try {
            $response = Http::timeout(5)->get($this->getUrl() . '/api/products');
            
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            Log::info("Server {$this->name} ({$this->port}) responded in {$responseTime}ms");
            
            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                    'response_time_ms' => $responseTime,
                    'server' => $this->name,
                    'port' => $this->port
                ];
            }
            
            return [
                'success' => false,
                'error' => 'HTTP ' . $response->status(),
                'server' => $this->name,
                'port' => $this->port
            ];
            
        } catch (\Exception $e) {
            Log::error("Server {$this->name} ({$this->port}) failed: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'server' => $this->name,
                'port' => $this->port
            ];
        }
    }

    public function getHealthStatus(): array
    {
        return [
            'name' => $this->name,
            'host' => $this->host,
            'port' => $this->port,
            'url' => $this->getUrl(),
            'load' => $this->load,
            'available' => $this->isAvailable()
        ];
    }
}