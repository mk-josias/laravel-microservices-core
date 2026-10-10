<?php

declare(strict_types=1);

namespace Microservices\Services\Stream;

use Microservices\Config\Streamer;
use Microservices\Contracts\Stream\Bus;
use Microservices\Contracts\Stream\Event;
use Microservices\Services\Stream\Outbox\Writer;

/**
 * The single emission path: an event becomes an envelope stamped with its emitting service, then
 * goes to its stream's outbox or straight onto its stream. Service code never learns which one
 * is wired.
 */
final class Emitter implements Bus
{
    public function __construct(
        private readonly EnvelopeFactory $envelopes,
        private readonly TransportManager $transports,
        private readonly Writer $outbox,
        private readonly Streamer $config,
    ) {}

    public function emit(Event $event): void
    {
        $envelope = $this->envelopes->for($event);

        $this->config->usesOutbox($envelope->stream)
            ? $this->outbox->write($envelope)
            : $this->transports->stream($envelope->stream)->publish($envelope);
    }
}
