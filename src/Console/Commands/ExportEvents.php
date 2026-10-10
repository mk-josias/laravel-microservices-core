<?php

declare(strict_types=1);

namespace Microservices\Console\Commands;

use Illuminate\Console\Command;
use Microservices\Console\ArchiveOptions;
use Microservices\Console\LocalServicesOption;
use Microservices\Services\Stream\Outbox\Archive;

/** Keeps the outbox small without losing the journal: published rows move to an archive. */
final class ExportEvents extends Command
{
    use ArchiveOptions;
    use LocalServicesOption;

    protected $signature = 'stream:export
        {target : Where the store appends the rows, e.g. a JSON-lines file for `file`}
        {--store= : The archive store (default: microservices.events.archive)}
        {--service=* : Limit to these services}
        {--stream= : Only rows of this stream}
        {--since= : Only rows emitted on or after this date}
        {--until= : Only rows emitted before this date}
        {--where=* : Only rows matching column=value, or payload.field=value}
        {--acknowledged : Only rows every consumer has acknowledged}
        {--batch=1000 : Rows read and deleted per batch}';

    protected $description = 'Move published outbox rows to an archive, then delete them from the outbox.';

    public function handle(Archive $archive): int
    {
        $store = $this->archiveStore();

        foreach ($this->selectedServices() as $service) {
            $count = $archive->export(
                service: $service,
                store: $store,
                target: (string) $this->argument('target'),
                since: $this->stringOption('since'),
                until: $this->stringOption('until'),
                stream: $this->stringOption('stream'),
                where: $this->whereOption(),
                acknowledged: (bool) $this->option('acknowledged'),
                batch: max(1, (int) $this->option('batch')),
            );

            $this->line("→ {$service}: {$count} exported");
        }

        return self::SUCCESS;
    }
}
