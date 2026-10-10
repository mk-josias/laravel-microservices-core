<?php

declare(strict_types=1);

namespace Microservices\Contracts\Stream;

/** What the bus emits: a name and a payload; Events\Event implements the rest with its defaults. */
interface Event
{
    public function name(): string;

    /** @return array<string, mixed> */
    public function payload(): array;

    /** The emitting service, when it is not the one the event class belongs to. */
    public function emitter(): ?string;

    /** @return list<string> the only services that handle it; empty for every service */
    public function recipients(): array;

    /** The stream of microservices.events.streams it travels on; null for the default one. */
    public function stream(): ?string;

    /** The version of the payload's shape: raised when a field is renamed, removed or changes meaning. */
    public function version(): int;
}
