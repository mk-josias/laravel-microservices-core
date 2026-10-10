<?php

declare(strict_types=1);

namespace Microservices\Contracts\Stream;

/**
 * A service's reaction to an event, declared in microservices.events.listen. The signature is
 * transport-neutral — a distributed consumer only ever has the name and the raw payload, so the
 * in-process driver hands over the same thing.
 */
interface Handler
{
    /** @param array<string, mixed> $payload */
    public function handle(string $name, array $payload): void;
}
