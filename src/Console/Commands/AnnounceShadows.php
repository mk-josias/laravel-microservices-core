<?php

declare(strict_types=1);

namespace Microservices\Console\Commands;

use Illuminate\Console\Command;
use Microservices\Services\Shadows\ShadowRegistry;

/** Run by the owner of a source table: every row goes out again, for copies created after it. */
final class AnnounceShadows extends Command
{
    protected $signature = 'shadows:announce
        {source : The source table, e.g. iam_users}
        {--keepers=* : Only these services update their copy (default: every one keeping it)}';

    protected $description = 'Announce every row of a source table to the services keeping a copy of it.';

    public function handle(ShadowRegistry $catalog): int
    {
        $source = $catalog->sourceOf((string) $this->argument('source'));

        if ($source === null) {
            $this->error('No service running here owns that table as a shadow source.');

            return self::FAILURE;
        }

        $this->line('→ announced '.$source->announceAll(keepers: array_values(array_map(strval(...), (array) $this->option('keepers')))));

        return self::SUCCESS;
    }
}
