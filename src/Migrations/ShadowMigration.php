<?php

declare(strict_types=1);

namespace Microservices\Migrations;

use Illuminate\Database\Migrations\Migration;
use Microservices\Contracts\Colocation;
use Microservices\Services\Shadows\ShadowRegistry;

/** The migration of a copy: it creates the table the keeper's copy model reads. */
abstract class ShadowMigration extends Migration
{
    /** The service whose database the migration runs in, when a migrate command runs it for several. */
    public static string $keeper = '';

    /** The source table the copy mirrors. */
    abstract protected function source(): string;

    protected function table(): string
    {
        $keeper = static::$keeper !== '' ? static::$keeper : (string) app(Colocation::class)->current();

        $shadow = app(ShadowRegistry::class)->shadowsOf($this->source(), $keeper)[0] ?? null;

        return $shadow === null ? $this->source() : (new $shadow)->getTable();
    }
}
