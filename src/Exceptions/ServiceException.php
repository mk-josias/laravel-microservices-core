<?php

declare(strict_types=1);

namespace Microservices\Exceptions;

use RuntimeException;

/** A service that cannot be resolved, or a call or an event it cannot take. */
final class ServiceException extends RuntimeException
{
    public static function outsideService(string $class): self
    {
        return new self("Cannot resolve the service of [{$class}]: it belongs to no service this process knows.");
    }

    public static function unknownRpcMethod(string $service, string $contract, string $method): self
    {
        return new self("Service [{$service}] answers no [{$contract}::{$method}()]: only a method of a contract it declares in \$services can be called.");
    }

    public static function unmappedRpcService(string $class): self
    {
        return new self("[{$class}] is in no \$rpc map: its contract is unknown.");
    }

    public static function eventVersionAhead(string $name, int $version, int $known): self
    {
        return new self("Event [{$name}] arrived in version {$version}, and this process reads it up to version {$known}: deploy its consumer before it is handled.");
    }
}
