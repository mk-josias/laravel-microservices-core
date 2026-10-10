<?php

declare(strict_types=1);

namespace Microservices\Contracts\Stream;

use Microservices\Data\Envelope;

/** A transport that names each entry it publishes, and tells when every consumer has acknowledged one. */
interface TracksAcknowledgements
{
    /** @return string the id of the entry in the stream */
    public function publishTracked(Envelope $envelope): string;

    public function isAcknowledged(string $entryId): bool;
}
