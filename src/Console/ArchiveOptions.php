<?php

declare(strict_types=1);

namespace Microservices\Console;

use Microservices\Contracts\Stream\ArchiveStore;
use Microservices\Services\Stream\Outbox\ArchiveStores;

/** The options stream:export and stream:import share. */
trait ArchiveOptions
{
    protected function archiveStore(): ArchiveStore
    {
        $name = $this->stringOption('store') ?? (string) config('microservices.events.archive', 'file');

        return $this->laravel->make(ArchiveStores::class)->store($name);
    }

    protected function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @return array<string, string> */
    protected function whereOption(): array
    {
        $where = [];

        foreach ((array) $this->option('where') as $filter) {
            [$column, $value] = array_pad(explode('=', (string) $filter, 2), 2, '');
            $where[$column] = $value;
        }

        return $where;
    }
}
