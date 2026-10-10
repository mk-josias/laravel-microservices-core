<?php

declare(strict_types=1);

namespace Microservices\Services;

use Closure;
use Microservices\Config\Services;
use Microservices\Contracts\Colocation;

/**
 * One application, one service (microservices.name). A class belongs to the service whose
 * declared namespace holds it — the contract and RpcService of a service it calls — and
 * otherwise to this one.
 */
final readonly class SingleService implements Colocation
{
    public function __construct(private Services $config) {}

    public function local(): array
    {
        $name = $this->config->getName();

        return $name === null ? [] : [$name];
    }

    public function serviceOf(string $class): ?string
    {
        $best = null;
        $length = 0;

        foreach ($this->config->getNamespaces() as $service => $namespace) {
            if (str_starts_with($class, $namespace.'\\') && strlen($namespace) > $length) {
                [$best, $length] = [$service, strlen($namespace)];
            }
        }

        return $best ?? $this->config->getName();
    }

    public function current(): ?string
    {
        return $this->config->getName();
    }

    public function within(?string $service, Closure $callback): mixed
    {
        return $callback();
    }

    public function connection(string $service): ?string
    {
        return null;
    }

    public function classPath(string $service): string
    {
        return app_path();
    }
}
