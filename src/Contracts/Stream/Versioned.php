<?php

declare(strict_types=1);

namespace Microservices\Contracts\Stream;

/** A payload whose shape has changed: the version this code reads, and how an older payload is lifted one version up. */
interface Versioned
{
    public static function version(): int;

    /**
     * @param  array<string, mixed>  $payload  as written in version $from
     * @return array<string, mixed> the same payload in version $from + 1
     */
    public static function upcast(int $from, array $payload): array;
}
