<?php

declare(strict_types=1);

namespace Microservices\Console\Commands;

use Illuminate\Console\Command;
use Microservices\Console\LocalServicesOption;
use Microservices\Services\Stream\Outbox\Relay;

/**
 * The publisher role: the only process that puts outbox rows on the wire. One per service —
 * two would interleave a service's rows and break the order consumers replay them in.
 */
final class PublishEvents extends Command
{
    use LocalServicesOption;

    protected $signature = 'stream:publish
        {--service=* : Limit the sweep to these services}
        {--batch=100 : Rows claimed per pass}
        {--sleep=1 : Seconds between passes}
        {--once : Sweep once and exit}';

    protected $description = 'Relay pending outbox publications of the local services to the events transport.';

    public function handle(Relay $relay): int
    {
        $services = $this->selectedServices();
        $batch = (int) $this->option('batch');

        if ($services === []) {
            $this->error('No local service to sweep: set microservices.name.');

            return self::FAILURE;
        }

        do {
            $relayed = 0;

            foreach ($services as $service) {
                $relayed += $relay->drain($service, $batch);
            }

            if ($relayed > 0) {
                $this->line("→ published {$relayed}");
            }

            if (! $this->option('once')) {
                sleep(max(1, (int) $this->option('sleep')));
            }
        } while (! $this->option('once'));

        return self::SUCCESS;
    }
}
