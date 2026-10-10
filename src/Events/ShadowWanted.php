<?php

declare(strict_types=1);

namespace Microservices\Events;

/** A service keeping a copy asks the owner of the source table to announce every row, to it alone. */
final class ShadowWanted extends Event
{
    public const string NAME = 'microservices.shadow.wanted';

    public function __construct(
        private readonly string $keeper,
        private readonly string $owner,
        private readonly string $source,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    /** @return array{source: string, keeper: string} */
    public function payload(): array
    {
        return ['source' => $this->source, 'keeper' => $this->keeper];
    }

    public function emitter(): string
    {
        return $this->keeper;
    }

    /** @return list<string> */
    public function recipients(): array
    {
        return [$this->owner];
    }
}
