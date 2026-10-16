<?php

declare(strict_types=1);

namespace Microservices\Transports\Stream;

use Microservices\Contracts\Stream\Transport;
use Microservices\Data\Envelope;

/**
 * An in-memory stream for tests: publishing appends, consuming hands every envelope of the
 * subscribed channels to the handler once per consumer, then returns instead of blocking.
 */
final class ArrayTransport implements Transport
{
    /** @var list<Envelope> */
    private array $stream = [];

    /** @var array<string, int> position reached by each consumer */
    private array $cursors = [];

    public function publish(Envelope $envelope): void
    {
        $this->stream[] = $envelope;
    }

    public function consume(array $consumers, array $channels, callable $handle): void
    {
        foreach ($consumers as $consumer) {
            $position = $this->cursors[$consumer] ?? 0;

            for ($count = count($this->stream); $position < $count; $position++) {
                $envelope = $this->stream[$position];

                if (in_array($envelope->emitter, $channels, true)) {
                    $handle($envelope, $consumer);
                }

                $this->cursors[$consumer] = $position + 1;
            }
        }
    }

    public function stop(): void {}

    /** @return list<Envelope> */
    public function published(): array
    {
        return $this->stream;
    }
}
