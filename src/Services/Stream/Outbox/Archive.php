<?php

declare(strict_types=1);

namespace Microservices\Services\Stream\Outbox;

use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Microservices\Config\Streamer;
use Microservices\Contracts\Colocation;
use Microservices\Contracts\Stream\ArchiveStore;
use Microservices\Contracts\Stream\TracksAcknowledgements;
use Microservices\Exceptions\ConfigurationException;
use Microservices\Services\Stream\TransportManager;
use stdClass;

/** Moves published outbox rows to an archive store and back, in batches. */
final class Archive
{
    private const array COLUMNS = ['id', 'emitter', 'name', 'payload', 'headers', 'emitted_at', 'recipients', 'stream', 'version'];

    public function __construct(
        private readonly DatabaseManager $db,
        private readonly Colocation $colocation,
        private readonly TransportManager $transports,
        private readonly Streamer $config,
    ) {}

    /**
     * Writes the service's matching published rows to $target, then deletes them. With $acknowledged,
     * it stops at the first row some consumer has not acknowledged yet.
     *
     * @param  array<string, string>  $where  column, or payload.{field}, => value
     */
    public function export(
        string $service,
        ArchiveStore $store,
        string $target,
        ?string $since = null,
        ?string $until = null,
        ?string $stream = null,
        array $where = [],
        bool $acknowledged = false,
        int $batch = 1000,
    ): int {
        $exported = 0;

        do {
            $rows = $this->published($service, $since, $until, $stream, $where)->orderBy('sequence')->limit($batch)->get();
            $fetched = $rows->count();

            if ($acknowledged) {
                $rows = $rows->takeWhile(fn (stdClass $row): bool => $this->isAcknowledged($row));
            }

            $store->write($target, $rows->map(fn (stdClass $row): array => $this->line($row))->values()->all());

            $this->table($service)->whereIn('sequence', $rows->pluck('sequence')->all())->delete();
            $exported += $rows->count();
        } while ($fetched === $batch && $rows->count() === $fetched);

        return $exported;
    }

    private function isAcknowledged(stdClass $row): bool
    {
        $transport = $this->transports->stream((string) $row->stream);

        if (! $transport instanceof TracksAcknowledgements) {
            throw ConfigurationException::untrackedAcknowledgements((string) $row->stream);
        }

        return $row->stream_id !== null && $transport->isAcknowledged((string) $row->stream_id);
    }

    /**
     * Puts the matching rows of $source back as pending publications of their emitter, in order; a row
     * already there is skipped.
     *
     * @param  list<string>  $services  the emitters to take; empty for all of them
     * @param  array<string, string>  $where  column, or payload.{field}, => value
     */
    public function import(
        ArchiveStore $store,
        string $source,
        array $services = [],
        ?string $since = null,
        ?string $until = null,
        ?string $stream = null,
        array $where = [],
        int $batch = 1000,
    ): int {
        self::assertFilters($where);
        $imported = 0;
        $pending = [];

        foreach ($store->read($source) as $row) {
            if (! $this->matches($row, $services, $since, $until, $stream, $where)) {
                continue;
            }

            // A row exported before payloads had a version: every row of a batch needs the same columns.
            $pending[(string) $row['emitter']][] = $row + ['version' => 1];

            if (count($pending, COUNT_RECURSIVE) - count($pending) >= $batch) {
                $imported += $this->insert($pending);
                $pending = [];
            }
        }

        return $imported + $this->insert($pending);
    }

    /**
     * The same filters as an export, on a row read back.
     *
     * @param  array<string, mixed>  $row
     * @param  list<string>  $services
     * @param  array<string, string>  $where
     */
    private function matches(array $row, array $services, ?string $since, ?string $until, ?string $stream, array $where): bool
    {
        $emittedAt = (string) ($row['emitted_at'] ?? '');

        if (($services !== [] && ! in_array($row['emitter'] ?? null, $services, true))
            || ($since !== null && strcmp($emittedAt, $since) < 0)
            || ($until !== null && strcmp($emittedAt, $until) >= 0)
            || ($stream !== null && ($row['stream'] ?? null) !== $stream)) {
            return false;
        }

        foreach ($where as $column => $value) {
            $path = explode('.', $column);
            $field = array_shift($path) === 'payload'
                ? data_get(json_decode((string) ($row['payload'] ?? '{}'), true), implode('.', $path))
                : $row[$column] ?? null;

            if ((string) $field !== $value) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, list<array<string, mixed>>> $pending */
    private function insert(array $pending): int
    {
        $inserted = 0;

        foreach ($pending as $emitter => $rows) {
            $inserted += $this->table($emitter)->insertOrIgnore($rows);
        }

        return $inserted;
    }

    /** @param array<string, string> $where */
    private function published(string $service, ?string $since, ?string $until, ?string $stream, array $where): Builder
    {
        self::assertFilters($where);

        $query = $this->table($service)
            ->where('emitter', $service)
            ->whereNotNull('published_at')
            ->when($since !== null, fn (Builder $q) => $q->where('emitted_at', '>=', $since))
            ->when($until !== null, fn (Builder $q) => $q->where('emitted_at', '<', $until))
            ->when($stream !== null, fn (Builder $q) => $q->where('stream', $stream));

        foreach ($where as $column => $value) {
            $query->where(str_replace('.', '->', $column), $value);
        }

        return $query;
    }

    /** @param array<string, string> $where */
    private static function assertFilters(array $where): void
    {
        foreach (array_keys($where) as $column) {
            if (preg_match('/^(name|emitter|stream|payload(\.\w+)+)$/', $column) !== 1) {
                throw ConfigurationException::invalidExportFilter($column);
            }
        }
    }

    /** @return array<string, mixed> */
    private function line(stdClass $row): array
    {
        return array_intersect_key((array) $row, array_flip(self::COLUMNS));
    }

    private function table(string $service): Builder
    {
        return $this->db->connection($this->colocation->connection($service))->table($this->config->getPublicationsTable());
    }
}
