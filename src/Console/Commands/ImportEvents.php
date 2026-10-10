<?php

declare(strict_types=1);

namespace Microservices\Console\Commands;

use Illuminate\Console\Command;
use Microservices\Console\ArchiveOptions;
use Microservices\Services\Stream\Outbox\Archive;

/** Replays an export: its rows become pending publications again, put on the wire by the publisher. */
final class ImportEvents extends Command
{
    use ArchiveOptions;

    protected $signature = 'stream:import
        {source : What stream:export wrote, e.g. its JSON-lines file for `file`}
        {--store= : The archive store (default: microservices.events.archive)}
        {--service=* : Only rows these services emitted}
        {--stream= : Only rows of this stream}
        {--since= : Only rows emitted on or after this date}
        {--until= : Only rows emitted before this date}
        {--where=* : Only rows matching column=value, or payload.field=value}
        {--batch=1000 : Rows inserted per batch}';

    protected $description = 'Queue the rows of an export for publication again.';

    public function handle(Archive $archive): int
    {
        $count = $archive->import(
            store: $this->archiveStore(),
            source: (string) $this->argument('source'),
            services: array_values(array_map(strval(...), (array) $this->option('service'))),
            since: $this->stringOption('since'),
            until: $this->stringOption('until'),
            stream: $this->stringOption('stream'),
            where: $this->whereOption(),
            batch: max(1, (int) $this->option('batch')),
        );

        $this->line("→ {$count} imported");

        return self::SUCCESS;
    }
}
