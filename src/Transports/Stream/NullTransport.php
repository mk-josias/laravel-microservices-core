<?php

declare(strict_types=1);

namespace Microservices\Transports\Stream;

use Microservices\Contracts\Stream\Transport;
use Microservices\Data\Envelope;

/** Drops every envelope — for tests, and for running a node with its reactions cut. */
final class NullTransport implements Transport
{
    public function publish(Envelope $envelope): void {}

    public function consume(string $consumer, array $channels, callable $handle): void {}

    public function stop(): void {}
}
