<?php

declare(strict_types=1);

namespace Microservices\Handlers;

use Microservices\Contracts\Colocation;
use Microservices\Contracts\Stream\Handler;
use Microservices\Contracts\Stream\Idempotent;
use Microservices\Services\Shadows\ShadowRegistry;

/** Writes an announced source row into the copies of the consuming service, or of every local keeper, each in its own context. */
final readonly class SyncShadows implements Handler, Idempotent
{
    public function __construct(
        private ShadowRegistry $catalog,
        private Colocation $colocation,
    ) {}

    public function handle(string $name, array $payload): void
    {
        $source = (string) $payload['source'];
        /** @var array<string, mixed> $attributes */
        $attributes = (array) ($payload['attributes'] ?? []);
        $key = is_int($payload['key']) ? $payload['key'] : (string) $payload['key'];
        $current = $this->colocation->current();

        foreach ($current !== null ? [$current] : $this->catalog->keepersOf($source) as $keeper) {
            $this->colocation->within($keeper, function () use ($source, $keeper, $key, $attributes): void {
                foreach ($this->catalog->shadowsOf($source, $keeper) as $shadow) {
                    $shadow::sync($key, $attributes);
                }
            });
        }
    }
}
