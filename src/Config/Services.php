<?php

declare(strict_types=1);

namespace Microservices\Config;

use Illuminate\Contracts\Config\Repository;

/** microservices.name and microservices.services, typed and defaulted; read live, never snapshotted. */
final readonly class Services
{
    public function __construct(private Repository $config) {}

    public function getName(): ?string
    {
        $name = $this->config->get('microservices.name');

        return is_string($name) && $name !== '' ? $name : null;
    }

    /** @return list<string> this service and every one it declares */
    public function getDeclared(): array
    {
        $declared = array_map(strval(...), array_keys((array) $this->config->get('microservices.services', [])));
        $name = $this->getName();

        return array_values(array_unique($name === null ? $declared : [$name, ...$declared]));
    }

    /** @return array<string, string> service => the namespace of its contracts and RpcService here */
    public function getNamespaces(): array
    {
        $namespaces = [];

        foreach ((array) $this->config->get('microservices.services', []) as $service => $options) {
            if (is_array($options) && is_string($options['namespace'] ?? null) && $options['namespace'] !== '') {
                $namespaces[(string) $service] = trim($options['namespace'], '\\');
            }
        }

        return $namespaces;
    }
}
