<?php

declare(strict_types=1);

namespace Microservices\Services\Stream\Outbox;

use Closure;
use Illuminate\Contracts\Container\Container;
use Microservices\Contracts\Stream\ArchiveStore;
use Microservices\Exceptions\ConfigurationException;

/** The archive stores by name: `file` ships; extend('s3', fn ($app) => …) registers any other. */
final class ArchiveStores
{
    /** @var array<string, Closure(Container): ArchiveStore> */
    private array $creators = [];

    public function __construct(private readonly Container $container) {}

    public function store(string $name): ArchiveStore
    {
        if (isset($this->creators[$name])) {
            return ($this->creators[$name])($this->container);
        }

        return match ($name) {
            'file' => new FileArchiveStore,
            default => throw ConfigurationException::unknownArchiveStore($name),
        };
    }

    /** @param Closure(Container): ArchiveStore $creator */
    public function extend(string $name, Closure $creator): static
    {
        $this->creators[$name] = $creator;

        return $this;
    }
}
