<?php

declare(strict_types=1);

namespace Microservices\Contracts\Stream;

/** Where stream:export puts published outbox rows, and where stream:import reads them back, in order. */
interface ArchiveStore
{
    /**
     * Appends the rows to $target, after the ones already there.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function write(string $target, array $rows): void;

    /**
     * The rows of $source, in the order they were written.
     *
     * @return iterable<array<string, mixed>>
     */
    public function read(string $source): iterable;
}
