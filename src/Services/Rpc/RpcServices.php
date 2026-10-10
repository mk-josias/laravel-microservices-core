<?php

declare(strict_types=1);

namespace Microservices\Services\Rpc;

/** Each contract bound to an RpcService, with the service it calls. */
final class RpcServices
{
    /** @var array<class-string, array{service: string|null, rpc: class-string}> */
    private array $services = [];

    /** @param array<class-string, array{service: string|null, rpc: class-string}> $services */
    public function add(array $services): void
    {
        $this->services = [...$this->services, ...$services];
    }

    /** @return array<class-string, array{service: string|null, rpc: class-string}> */
    public function all(): array
    {
        return $this->services;
    }

    /** @return class-string|null the contract $rpc is mapped to */
    public function contractOf(string $rpc): ?string
    {
        foreach ($this->services as $contract => $service) {
            if ($service['rpc'] === $rpc) {
                return $contract;
            }
        }

        return null;
    }
}
