<?php

declare(strict_types=1);

namespace Microservices\Events;

use Microservices\Contracts\Stream\Event as EventContract;

/**
 * Base for events: a concrete event returns its name and its typed payload as an array. Nothing
 * else — identity, emitting service, propagated context and timestamp are the Envelope's
 * business, so the payload stays business only.
 */
abstract class Event implements EventContract
{
    abstract public function name(): string;

    /** @return array<string, mixed> */
    abstract public function payload(): array;

    /** The emitting service, when it is not the one the event class belongs to. */
    public function emitter(): ?string
    {
        return null;
    }

    /** @return list<string> the only services that handle it; empty for every service */
    public function recipients(): array
    {
        return [];
    }

    /** The stream of microservices.events.streams it travels on; null for the default one. */
    public function stream(): ?string
    {
        return null;
    }

    /** The version of the payload's shape: raised when a field is renamed, removed or changes meaning. */
    public function version(): int
    {
        return 1;
    }
}
