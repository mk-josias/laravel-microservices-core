<?php

declare(strict_types=1);

namespace Microservices\Services\Stream;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Date;
use Microservices\Config\Streamer;
use Microservices\Contracts\Colocation;
use Microservices\Contracts\Stream\Handler;
use Microservices\Contracts\Stream\Idempotent;
use Microservices\Contracts\Stream\RedeliversEnvelopes;
use Microservices\Data\Envelope;
use Microservices\Events\ShadowChanged;
use Microservices\Events\ShadowWanted;
use Microservices\Exceptions\ConfigurationException;
use Microservices\Exceptions\ServiceException;
use Microservices\Handlers\AnnounceShadowSource;
use Microservices\Handlers\SyncShadows;

/**
 * Runs the local handlers of an envelope. Every transport ends here — in-process delivery and a
 * broker consumer take exactly the same path, so a handler behaves the same wherever its emitter runs.
 *
 * When delivery is at-least-once, each run is guarded: the mark in `event_consumptions` and the
 * handler's own writes share one transaction on the consuming service's database, so a replay is
 * a no-op and a failed handler leaves no mark behind. Handlers become idempotent by
 * construction — their author writes nothing. The guard covers what a handler writes to its
 * database; effects outside it (mail, third-party calls) stay at-least-once.
 */
final class Dispatcher
{
    public function __construct(
        private readonly Container $container,
        private readonly Streamer $config,
        private readonly Colocation $colocation,
        private readonly DatabaseManager $db,
        private readonly TransportManager $transports,
        private readonly PayloadVersions $versions,
    ) {}

    /** With a $consumer, only its handlers run: a process running several services has one consumer per service. */
    public function dispatch(Envelope $envelope, ?string $consumer = null): void
    {
        $guarded = $this->guarded($envelope->stream);

        foreach ($this->handlersFor($envelope->name) as $class) {
            $owner = $this->colocation->serviceOf($class);

            if ($consumer !== null && $owner !== null && $owner !== $consumer) {
                continue;
            }

            $guarded && ! is_subclass_of($class, Idempotent::class)
                ? $this->runGuarded($class, $envelope)
                : $this->run($class, $envelope, $owner ?? $consumer);
        }
    }

    /** @return list<class-string> the package's own handlers, then the application's */
    public function handlersFor(string $name): array
    {
        $builtIn = [
            ShadowChanged::NAME => [SyncShadows::class],
            ShadowWanted::NAME => [AnnounceShadowSource::class],
        ];

        return [...($builtIn[$name] ?? []), ...$this->config->getHandlers($name)];
    }

    public function guarded(?string $stream = null): bool
    {
        $stream ??= $this->config->getDefaultStream();

        return $this->config->getGuard()
            ?? ($this->config->usesOutbox($stream) || $this->transports->stream($stream) instanceof RedeliversEnvelopes);
    }

    /** @param class-string $class */
    private function runGuarded(string $class, Envelope $envelope): void
    {
        $service = $this->colocation->serviceOf($class)
            ?? throw ServiceException::outsideService($class);

        $connection = $this->db->connection($this->colocation->connection($service));

        $connection->transaction(function () use ($connection, $class, $envelope, $service): void {
            $claimed = $connection->table($this->config->getConsumptionsTable())->insertOrIgnore([
                'event_id' => $envelope->id,
                'handler' => $class,
                'consumed_at' => Date::now(),
            ]);

            if ($claimed === 0) {
                return; // already handled — the replay stops here
            }

            $this->run($class, $envelope, $service);
        });
    }

    /**
     * @param  class-string  $class
     * @param  string|null  $service  the handler's own service, or the consumer's for one of the package
     */
    private function run(string $class, Envelope $envelope, ?string $service): void
    {
        $handler = $this->container->make($class);

        if (! $handler instanceof Handler) {
            throw ConfigurationException::invalidHandler($class, Handler::class);
        }

        $payload = $this->versions->lift($envelope);

        $this->colocation->within(
            $service,
            fn () => $this->withContext($envelope->headers, static fn () => $handler->handle($envelope->name, $payload)),
        );
    }

    /**
     * Runs the handler under the emitter's propagated context, then puts the caller's back.
     *
     * @param  array<string, mixed>  $headers
     */
    private function withContext(array $headers, callable $callback): void
    {
        $previous = Context::only(array_keys($headers));
        Context::add($headers);

        try {
            $callback();
        } finally {
            Context::forget(array_keys($headers));
            Context::add($previous);
        }
    }
}
