<?php

declare(strict_types=1);

namespace Microservices\Console\Commands;

use Illuminate\Console\Command;
use Microservices\Console\LocalServicesOption;
use Microservices\Services\Stream\Outbox\Relay;

/**
 * For the day the broker is emptied while the outboxes are intact: the stream is rebuilt from
 * each service's own journal, in order. The consumption guard turns what consumers already
 * applied into no-ops, so replaying costs time and changes nothing.
 */
final class RepublishEvents extends Command
{
    use LocalServicesOption;

    protected $signature = 'stream:republish
        {--service=* : Limit to these services}
        {--since= : Only rows emitted on or after this date}
        {--force : Skip the confirmation}';

    protected $description = 'Queue already-published outbox rows again, to rebuild an emptied stream.';

    public function handle(Relay $relay): int
    {
        $since = $this->option('since');

        foreach ($this->selectedServices() as $service) {
            if (! $this->option('force') && ! $this->confirm("Republish the outbox of [{$service}]?")) {
                continue;
            }

            $count = $relay->requeue($service, is_string($since) ? $since : null);

            $this->line("→ {$service}: {$count} requeued");
        }

        return self::SUCCESS;
    }
}
