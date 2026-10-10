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
 * The consumer role: reads the envelopes published by every service and runs this service's
 * handlers. The consuming service names the subscription (its own cursor), so adding a consumer
 * disturbs none of the others.
 */
final class ConsumeEvents extends Command
{
    protected $signature = 'stream:consume
        {--service= : The consuming service (default: the one running, or the only local one)}
        {--stream= : The stream to read (default: microservices.events.stream)}';

    protected $description = 'Consume events from a stream and run this service handlers.';

    public function handle(Colocation $colocation, Services $services, TransportManager $transports, Dispatcher $dispatcher): int
    {
        $transport = $transports->stream($this->option('stream') !== null ? (string) $this->option('stream') : null);
        $consumer = $this->consumer($colocation);

        if ($consumer === null) {
            $this->error('Several services run here — name the consuming one with --service.');

            return self::FAILURE;
        }

        $channels = $services->getDeclared();

        $this->info("Consuming as [{$consumer}] from: ".implode(', ', $channels));

        $this->stopGracefullyOnSignal($transport);

        $transport->consume($consumer, $channels, function (Envelope $envelope) use ($dispatcher, $consumer): void {
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

    private function consumer(Colocation $colocation): ?string
    {
        $service = $this->option('service');

        if (is_string($service) && $service !== '') {
            return $service;
        }

        $local = $colocation->local();

        return $colocation->current() ?? (count($local) === 1 ? $local[0] : null);
    }
}
