<?php

declare(strict_types=1);

namespace Microservices\Contracts\Stream;

/**
 * The single seam services emit events through. The binding decides what emitting means —
 * straight to the transport, or through the outbox — and service code never knows which.
 */
interface Bus
{
    public function emit(Event $event): void;
}
