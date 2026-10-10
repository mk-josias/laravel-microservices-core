<?php

declare(strict_types=1);

namespace Microservices\Contracts\Shadows;

/** A model other services keep a copy of; implemented by the ShadowSource trait. */
interface Shadowed
{
    /** @param list<string> $keepers the only services to update their copy; empty for all of them */
    public function announceShadow(bool $deleted = false, array $keepers = []): void;

    /**
     * Re-announces every row; returns how many went out.
     *
     * @param  list<string>  $keepers
     */
    public static function announceAll(int $chunk = 500, array $keepers = []): int;

    /** @return list<string> */
    public function shadowedFields(): array;
}
