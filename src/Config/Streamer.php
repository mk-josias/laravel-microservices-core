<?php

declare(strict_types=1);

namespace Microservices\Config;

use Illuminate\Contracts\Config\Repository;
use Microservices\Exceptions\ConfigurationException;

/** The microservices.events.* settings, typed and defaulted; read live, never snapshotted. */
final readonly class Streamer
{
    public function __construct(private Repository $config) {}

    public function getDefaultStream(): string
    {
        return (string) $this->config->get('microservices.events.stream', 'default');
    }

    /** @return array<string, mixed> */
    public function getStream(string $name): array
    {
        /** @var array<string, array<string, mixed>> $streams */
        $streams = $this->config->get('microservices.events.streams', []);

        return $streams[$name] ?? throw ConfigurationException::unknownStream($name);
    }

    /** The outbox table, in each emitting service's database. */
    public function getPublicationsTable(): string
    {
        return (string) $this->config->get('microservices.events.tables.publications', 'event_publications');
    }

    /** The consumption marks of the guard, in each consuming service's database. */
    public function getConsumptionsTable(): string
    {
        return (string) $this->config->get('microservices.events.tables.consumptions', 'event_consumptions');
    }

    public function usesOutbox(string $stream): bool
    {
        return (bool) $this->config->get("microservices.events.streams.{$stream}.outbox", false);
    }

    public function getGuard(): ?bool
    {
        $guard = $this->config->get('microservices.events.guard');

        return $guard === null ? null : (bool) $guard;
    }

    /** @return list<class-string> */
    public function getHandlers(string $event): array
    {
        /** @var array<string, list<class-string>> $listen */
        $listen = $this->config->get('microservices.events.listen', []);

        // Direct key access: event names carry dots, which config dot-notation would split.
        return $listen[$event] ?? [];
    }

    /** @return list<string> */
    public function getPropagate(): array
    {
        return array_values(array_map(strval(...), (array) $this->config->get('microservices.events.propagate', [])));
    }
}
