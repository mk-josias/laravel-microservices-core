<?php

declare(strict_types=1);

namespace Microservices\Services\Stream;

use Illuminate\Support\Facades\Context;
use Microservices\Config\Streamer;
use Microservices\Contracts\Colocation;
use Microservices\Contracts\Stream\Event;
use Microservices\Data\Envelope;
use Microservices\Exceptions\ServiceException;

/** Stamps an event with its emitting service and the propagated context — the only place that does. */
final class EnvelopeFactory
{
    public function __construct(
        private readonly Colocation $colocation,
        private readonly Streamer $config,
    ) {}

    public function for(Event $event): Envelope
    {
        $emitter = $event->emitter()
            ?? $this->colocation->serviceOf($event::class)
            ?? throw ServiceException::outsideService($event::class);

        return Envelope::for(
            event: $event,
            emitter: $emitter,
            headers: array_filter(Context::only($this->config->getPropagate()), static fn (mixed $value): bool => $value !== null),
            stream: $event->stream() ?? $this->config->getDefaultStream(),
        );
    }
}
