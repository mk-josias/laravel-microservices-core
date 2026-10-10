<?php

declare(strict_types=1);

namespace Microservices\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Microservices\Config\Streamer;
use Microservices\Contracts\Colocation;
use Throwable;

/** A consumption mark only matters while its event can still be redelivered: past the stream's retention it guards nothing. */
final class PruneConsumptions extends Command
{
    protected $signature = 'stream:prune-consumptions
        {--days=7 : Keep the marks of the last N days; must outlast how long an event can stay in the stream}';

    protected $description = 'Delete the consumption marks older than the given number of days, in every local service.';

    public function handle(Colocation $colocation, Streamer $config): int
    {
        $table = $config->getConsumptionsTable();
        $days = (int) $this->option('days');

        if ($days < 1) {
            $this->error('--days must be at least 1.');

            return self::FAILURE;
        }

        $before = Date::now()->subDays($days);

        $current = $colocation->current();

        foreach ($current !== null ? [$current] : $colocation->local() as $service) {
            try {
                $deleted = $colocation->within($service, static fn (): int => DB::table($table)->where('consumed_at', '<', $before)->delete());
                $this->line("→ {$service}: {$deleted} deleted");
            } catch (Throwable $e) {
                $this->warn("→ {$service}: skipped ({$e->getMessage()})");
            }
        }

        return self::SUCCESS;
    }
}
