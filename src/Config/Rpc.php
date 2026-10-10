<?php

declare(strict_types=1);

namespace Microservices\Config;

use Illuminate\Contracts\Config\Repository;
use Microservices\Exceptions\ConfigurationException;

/** The microservices.rpc.* settings, and each service's host; read live, never snapshotted. */
final readonly class Rpc
{
    public function __construct(private Repository $config) {}

    public function hasHost(string $service): bool
    {
        return $this->config->get("microservices.services.{$service}.host") !== null;
    }

    public function getHost(string $service): string
    {
        $host = $this->host($service);

        return (string) (is_array($host) ? ($host['url'] ?? '') : $host);
    }

    /** The transport a service's calls travel on: its host's `transport`, or microservices.rpc.transport. */
    public function getTransportOf(string $service): string
    {
        $host = $this->host($service);

        return (string) (is_array($host) && isset($host['transport']) ? $host['transport'] : $this->getDefaultTransport());
    }

    public function getDefaultTransport(): string
    {
        return (string) $this->config->get('microservices.rpc.transport', 'http');
    }

    /** @return array<string, mixed> */
    public function getTransport(string $name): array
    {
        /** @var array<string, array<string, mixed>> $transports */
        $transports = (array) $this->config->get('microservices.rpc.transports', []);

        return $transports[$name] ?? throw ConfigurationException::unknownRpcTransport($name);
    }

    /** @return string|array<string, mixed> */
    private function host(string $service): string|array
    {
        /** @var string|array<string, mixed>|null $host */
        $host = $this->config->get("microservices.services.{$service}.host");

        return $host ?? throw ConfigurationException::missingRpcHost($service);
    }

    /** Where a service answers a call, the same for caller and called: {service} and {method} are filled in. */
    public function getPath(): string
    {
        return trim((string) $this->config->get('microservices.rpc.path', '{service}/rpc/{method}'), '/');
    }

    /** The middleware group of the called side's routes, which checks the signature. */
    public function getMiddlewareGroup(): string
    {
        return (string) $this->config->get('microservices.rpc.middleware_group', 'rpc');
    }

    public function getSecret(): string
    {
        return (string) $this->config->get('microservices.rpc.secret', '');
    }

    /** Null is the application's default cache store. */
    public function getCacheStore(): ?string
    {
        $store = $this->config->get('microservices.rpc.cache');

        return is_string($store) && $store !== '' ? $store : null;
    }

    public function getSignatureTtl(): int
    {
        return (int) $this->config->get('microservices.rpc.signature_ttl', 30);
    }
}
