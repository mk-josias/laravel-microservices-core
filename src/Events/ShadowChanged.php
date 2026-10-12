<?php

declare(strict_types=1);

namespace Microservices\Events;

/** A source row as it now stands, announced to the services keeping a copy of it. */
final class ShadowChanged extends Event
{
    public const string NAME = 'microservices.shadow.changed';

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $keepers  the only services to update their copy; empty for all of them
     * @param  string|null  $stream  the stream the source model names; null for the default one
     */
    public function __construct(
        private readonly string $sourceService,
        private readonly string $source,
        private readonly int|string $key,
        private readonly array $attributes,
        private readonly array $keepers = [],
        private readonly ?string $stream = null,
    ) {}

    /** @return list<string> */
    public function recipients(): array
    {
        return $this->keepers;
    }

    public function stream(): ?string
    {
        return $this->stream;
    }

    public function name(): string
    {
        return self::NAME;
    }

    /** @return array{source: string, key: int|string, attributes: array<string, mixed>} */
    public function payload(): array
    {
        return ['source' => $this->source, 'key' => $this->key, 'attributes' => $this->attributes];
    }

    public function emitter(): string
    {
        return $this->sourceService;
    }
}
