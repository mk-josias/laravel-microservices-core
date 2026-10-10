<?php

declare(strict_types=1);

namespace Microservices\Contracts\Stream;

/** A transport that keeps envelopes after delivery, and drops only those every consumer acknowledged. */
interface TrimsStreams
{
    /** @return int how many entries were dropped from this stream */
    public function trim(): int;
}
