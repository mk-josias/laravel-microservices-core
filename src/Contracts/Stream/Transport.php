<?php

declare(strict_types=1);

namespace Microservices\Contracts\Stream;

use Microservices\Data\Envelope;

/**
 * How an envelope leaves this process and how it comes back. THE extension point: the package
 * ships `redis`, `queue`, `array` and `null`, anything else (kafka, amqp, an existing streamer package) plugs in
 * through the transport manager's extend() without touching the package.
 *
 * The acknowledgement contract is the smallest common denominator of every broker: the callback
 * returning normally acknowledges the message, the callback throwing does not — redelivery is
 * the broker's job, and the consumption guard is what makes redelivery safe.
 */
interface Transport
{
    public function publish(Envelope $envelope): void;

    /**
     * Blocking read loop, until stop() is called.
     *
     * @param  string  $consumer  the consuming service — its own cursor (group / queue)
     * @param  list<string>  $channels  the emitting services to subscribe to
     * @param  callable(Envelope): void  $handle
     */
    public function consume(string $consumer, array $channels, callable $handle): void;

    /** Asks the read loop to finish its current envelope and return — a graceful SIGTERM. */
    public function stop(): void;
}
