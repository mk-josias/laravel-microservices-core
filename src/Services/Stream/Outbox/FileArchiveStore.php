<?php

declare(strict_types=1);

namespace Microservices\Services\Stream\Outbox;

use Microservices\Contracts\Stream\ArchiveStore;
use SplFileObject;

/** A JSON-lines file per target: one row per line. */
final readonly class FileArchiveStore implements ArchiveStore
{
    public function write(string $target, array $rows): void
    {
        $file = new SplFileObject($target, 'a');

        foreach ($rows as $row) {
            $file->fwrite(json_encode($row, JSON_THROW_ON_ERROR)."\n");
        }
    }

    public function read(string $source): iterable
    {
        foreach (new SplFileObject($source, 'r') as $line) {
            $row = is_string($line) && trim($line) !== '' ? json_decode($line, true, 512, JSON_THROW_ON_ERROR) : null;

            if (is_array($row)) {
                yield $row;
            }
        }
    }
}
