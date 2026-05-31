<?php

namespace App\LoadBalancing;

class LoadBalancer
{
    public array $servers;
    public int $index = 0;

    public function __construct(array $servers)
    {
        $this->servers = $servers;
    }

    public function dispatch()
    {
        $server = $this->servers[$this->index];

        $this->index = ($this->index + 1) % count($this->servers);

        return $server->fetchAllProducts();
    }
    public function pickServer()
{
    $server = $this->servers[$this->index];
    $this->index = ($this->index + 1) % count($this->servers);
    return $server->name;
}

}
