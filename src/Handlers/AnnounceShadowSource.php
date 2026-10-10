<?php

declare(strict_types=1);

namespace Microservices\Handlers;

use Microservices\Contracts\Colocation;
use Microservices\Contracts\Stream\Handler;
use Microservices\Contracts\Stream\Idempotent;
use Microservices\Services\Shadows\ShadowRegistry;

/** Answers a copy that asks again: every row is re-announced to the asking service alone. */
final readonly class AnnounceShadowSource implements Handler, Idempotent
{
    public function __construct(
        private ShadowRegistry $catalog,
        private Colocation $colocation,
    ) {}

    public function handle(string $name, array $payload): void
    {
        $this->catalog->sourceOf((string) $payload['source'], $this->colocation->current())
            ?->announceAll(keepers: [(string) $payload['keeper']]);
    }
}
