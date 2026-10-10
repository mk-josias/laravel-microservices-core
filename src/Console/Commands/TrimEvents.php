<?php

declare(strict_types=1);

namespace Microservices\Console\Commands;

use Illuminate\Console\Command;
use Microservices\Contracts\Stream\TrimsStreams;
use Microservices\Services\Stream\TransportManager;

final class TrimEvents extends Command
{
    protected $signature = 'stream:trim
        {--stream= : The stream to trim (default: microservices.events.stream)}';

    protected $description = 'Drop the stream entries every consumer has acknowledged.';

    public function handle(TransportManager $transports): int
    {
        $transport = $transports->stream($this->option('stream') !== null ? (string) $this->option('stream') : null);

        if (! $transport instanceof TrimsStreams) {
            $this->info('This transport keeps nothing after delivery: nothing to trim.');

            return self::SUCCESS;
        }

        $this->line($transport->trim().' dropped');

        return self::SUCCESS;
    }
}
