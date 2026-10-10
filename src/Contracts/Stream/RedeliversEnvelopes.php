<?php

declare(strict_types=1);

namespace Microservices\Contracts\Stream;

/**
 * A transport that may hand the same envelope over more than once (an unacknowledged broker
 * entry coming back, an outbox row republished after a crash). Declaring it turns the
 * consumption guard on: at-least-once delivery is safe exactly when handlers are idempotent,
 * and the guard makes them idempotent by construction.
 */
interface RedeliversEnvelopes {}
