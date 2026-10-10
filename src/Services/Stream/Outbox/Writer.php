<?php

declare(strict_types=1);

namespace Microservices\Services\Stream\Outbox;

use Illuminate\Database\DatabaseManager;
use Microservices\Config\Streamer;
use Microservices\Contracts\Colocation;
use Microservices\Data\Envelope;

/**
 * Writes a publication row on the emitting service's own connection, so it lands inside the
 * caller's business transaction — one transactional write, no dual write. Putting it on the wire
 * is the publisher role's job alone (see Relay).
 */
final class Writer
{
    public function __construct(
        private readonly Colocation $colocation,
        private readonly DatabaseManager $db,
        private readonly Streamer $config,
    ) {}

    public function write(Envelope $envelope): void
    {
        $this->db->connection($this->colocation->connection($envelope->emitter))->table($this->config->getPublicationsTable())->insert([
            'id' => $envelope->id,
            'emitter' => $envelope->emitter,
            'name' => $envelope->name,
            'payload' => json_encode($envelope->payload, JSON_THROW_ON_ERROR),
            'headers' => json_encode($envelope->headers, JSON_THROW_ON_ERROR),
            'emitted_at' => $envelope->emittedAt,
            'recipients' => json_encode($envelope->recipients, JSON_THROW_ON_ERROR),
            'stream' => $envelope->stream,
            'version' => $envelope->version,
        ]);
    }
}
