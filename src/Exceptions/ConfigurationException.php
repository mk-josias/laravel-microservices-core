<?php

declare(strict_types=1);

namespace Microservices\Exceptions;

use RuntimeException;

/** A setting or a declared class the package cannot work with. */
final class ConfigurationException extends RuntimeException
{
    public static function invalidHandler(string $class, string $contract): self
    {
        return new self("Event handler [{$class}] must implement {$contract}.");
    }

    public static function unversionedPayload(string $class, string $contract): self
    {
        return new self("[{$class}] is in a \$payloads map: it must implement {$contract}.");
    }

    public static function unknownStream(string $name): self
    {
        return new self("Stream [{$name}] is not declared: add it to microservices.events.streams.");
    }

    public static function unknownDriver(string $driver, string $stream): self
    {
        $package = ['redis' => 'mk-josias/redis-event-stream', 'queue' => 'mk-josias/queue-event-stream'][$driver] ?? null;

        return new self($package === null
            ? "Stream driver [{$driver}] of stream [{$stream}] is not supported: register it with Services\\Stream\\TransportManager::extend()."
            : "Stream driver [{$driver}] of stream [{$stream}] comes with its own package: composer require {$package}.");
    }

    public static function unknownRpcTransport(string $name): self
    {
        return new self("RPC transport [{$name}] is not declared: add it to microservices.rpc.transports.");
    }

    public static function unknownRpcDriver(string $driver, string $transport): self
    {
        return new self("RPC driver [{$driver}] of transport [{$transport}] is not supported: register it with Services\Rpc\TransportManager::extend().");
    }

    public static function untrackedAcknowledgements(string $stream): self
    {
        return new self("The transport of stream [{$stream}] does not know who acknowledged what: --acknowledged needs one implementing Contracts\\Stream\\TracksAcknowledgements.");
    }

    public static function invalidExportFilter(string $column): self
    {
        return new self("Cannot filter an export on [{$column}]: use name, emitter, stream or payload.{field}.");
    }

    public static function unknownArchiveStore(string $name): self
    {
        return new self("Archive store [{$name}] is not supported: register it with Services\\Stream\\Outbox\\ArchiveStores::extend().");
    }

    public static function missingRpcHost(string $service): self
    {
        return new self("No RPC host configured for service [{$service}]: set microservices.services.{$service}.host.");
    }
}
