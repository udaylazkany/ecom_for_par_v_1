<?php

namespace App\LoadBalancing;

use Illuminate\Support\Facades\Log;

class LoadBalancer
{
    private array $servers;
    private int $currentIndex = 0;
    private string $strategy;

    public function __construct(array $servers, string $strategy = 'round-robin')
    {
        $this->servers = $servers;
        $this->strategy = $strategy;
    }

    public function pickServer(): ?Server
    {
        $availableServers = array_filter($this->servers, fn($s) => $s->isAvailable());
        
        if (empty($availableServers)) {
            Log::warning("No available servers!");
            return null;
        }
        
        switch ($this->strategy) {
            case 'round-robin':
                $server = $this->roundRobin($availableServers);
                break;
            case 'least-loaded':
                $server = $this->leastLoaded($availableServers);
                break;
            case 'random':
                $server = $this->random($availableServers);
                break;
            default:
                $server = $this->roundRobin($availableServers);
        }
        
        if ($server) {
            $server->incrementLoad();
        }
        
        return $server;
    }

    private function roundRobin(array $servers): ?Server
    {
        $serversArray = array_values($servers);
        $this->currentIndex = ($this->currentIndex + 1) % count($serversArray);
        return $serversArray[$this->currentIndex];
    }

    private function leastLoaded(array $servers): ?Server
    {
        usort($servers, fn($a, $b) => $a->getLoad() <=> $b->getLoad());
        return $servers[0];
    }

    private function random(array $servers): ?Server
    {
        return $servers[array_rand($servers)];
    }

    public function getServersStatus(): array
    {
        $status = [];
        foreach ($this->servers as $server) {
            $status[] = $server->getHealthStatus();
        }
        return $status;
    }

    public function distributeRequests(int $totalRequests, callable $callback): array
    {
        $results = [];
        
        for ($i = 0; $i < $totalRequests; $i++) {
            $server = $this->pickServer();
            if ($server) {
                $results[] = $callback($server);
                $server->decrementLoad();
            }
        }
        
        return $results;
    }
}