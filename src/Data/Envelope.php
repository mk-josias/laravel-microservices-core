<?php

declare(strict_types=1);

namespace Microservices\Data;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Microservices\Contracts\Stream\Event;

/**
 * The wire contract. Every transport carries this shape and nothing else — that is what makes
 * them interchangeable, and why the package owns the format rather than inheriting a broker
 * library's. Metadata lives here, never in the business payload.
 */
final readonly class Envelope
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers  context carried across services (microservices.events.propagate)
     * @param  list<string>  $recipients  the only services that handle it; empty for every service
     */
    public function __construct(
        public string $id,
        public string $emitter,
        public string $name,
        public array $payload,
        public array $headers,
        public CarbonImmutable $emittedAt,
        public array $recipients = [],
        public string $stream = 'default',
        public int $version = 1,
    ) {}

    /** @param array<string, mixed> $headers */
    public static function for(Event $event, string $emitter, array $headers = [], string $stream = 'default'): self
    {
        return new self(
            // uuid7: time-ordered, so the unique index stays local.
            id: (string) Str::uuid7(),
            emitter: $emitter,
            name: $event->name(),
            payload: $event->payload(),
            headers: $headers,
            emittedAt: Date::now()->toImmutable(),
            recipients: $event->recipients(),
            stream: $stream,
            version: $event->version(),
        );
    }

    public function isFor(string $service): bool
    {
        return $this->recipients === [] || in_array($service, $this->recipients, true);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'emitter' => $this->emitter,
            'name' => $this->name,
            'payload' => $this->payload,
            'headers' => $this->headers,
            'emitted_at' => $this->emittedAt->toIso8601ZuluString('microsecond'),
            'recipients' => $this->recipients,
            'stream' => $this->stream,
            'version' => $this->version,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            emitter: (string) $data['emitter'],
            name: (string) $data['name'],
            payload: (array) ($data['payload'] ?? []),
            headers: (array) ($data['headers'] ?? []),
            emittedAt: CarbonImmutable::parse((string) $data['emitted_at']),
            recipients: array_values(array_map(strval(...), (array) ($data['recipients'] ?? []))),
            stream: (string) ($data['stream'] ?? 'default'),
            version: (int) ($data['version'] ?? 1),
        );
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    public static function fromJson(string $json): self
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return self::fromArray($data);
    }
}
