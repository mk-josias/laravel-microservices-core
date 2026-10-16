<?php

declare(strict_types=1);

namespace Microservices\Console\Commands;

use Illuminate\Console\Command;
use Microservices\Config\Services;
use Microservices\Contracts\Colocation;
use Microservices\Contracts\Stream\Transport;
use Microservices\Data\Envelope;
use Microservices\Services\Stream\Dispatcher;
use Microservices\Services\Stream\TransportManager;

/**
 * The consumer role: reads the envelopes published by every service and runs the handlers of the
 * services that run here. Each consuming service names a subscription (its own cursor), so adding
 * a consumer disturbs none of the others.
 */
final class ConsumeEvents extends Command
{
    protected $signature = 'stream:consume
        {--service= : The consuming service (default: the one running, or every local one)}
        {--stream= : The stream to read (default: microservices.events.stream)}';

    protected $description = 'Consume events from a stream and run this service handlers.';

    public function handle(Colocation $colocation, Services $services, TransportManager $transports, Dispatcher $dispatcher): int
    {
        $transport = $transports->stream($this->option('stream') !== null ? (string) $this->option('stream') : null);
        $consumers = $this->consumers($colocation);

        if ($consumers === []) {
            $this->error('No service runs here — name the consuming one with --service.');

            return self::FAILURE;
        }

        $channels = $services->getDeclared();

        $this->info('Consuming as ['.implode(', ', $consumers).'] from: '.implode(', ', $channels));

        $this->stopGracefullyOnSignal($transport);

        $transport->consume($consumers, $channels, function (Envelope $envelope, string $consumer) use ($dispatcher): void {
            if ($envelope->isFor($consumer)) {
                $dispatcher->dispatch($envelope, $consumer);
            }
        });

        return self::SUCCESS;
    }

    /**
     * A container restart must not kill a handler mid-flight: on SIGTERM the loop finishes the
     * envelope it holds, then returns.
     */
    private function stopGracefullyOnSignal(Transport $transport): void
    {
        if (! extension_loaded('pcntl')) {
            return;
        }

        pcntl_async_signals(true);

        foreach ([SIGTERM, SIGINT] as $signal) {
            pcntl_signal($signal, function () use ($transport): void {
                $this->line('Stopping after the current envelope…');
                $transport->stop();
            });
        }
    }

    /** @return list<string> */
    private function consumers(Colocation $colocation): array
    {
        $service = $this->option('service');

        if (is_string($service) && $service !== '') {
            return [$service];
        }

        $current = $colocation->current();

        return $current !== null ? [$current] : $colocation->local();
    }
}
