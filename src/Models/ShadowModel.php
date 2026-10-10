<?php

declare(strict_types=1);

namespace Microservices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A local, read-only copy of another service's rows: same key as the source row, soft-deleted
 * when the source is, written only through sync(). A service keeping it lists it in microservices.shadows;
 * like any model it reads the running service's database, under its source's name unless $table says otherwise.
 *
 * @phpstan-consistent-constructor
 */
abstract class ShadowModel extends Model
{
    use SoftDeletes;

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    private bool $syncing = false;

    /** The service owning the source table, e.g. `iam`. */
    abstract public static function owner(): string;

    /** The source table this copy mirrors, e.g. `iam_users`. */
    abstract public static function sourceTable(): string;

    /** The source's own name; a keeper sharing its database with the owner, or keeping a second form of it, sets $table. */
    public function getTable(): string
    {
        return $this->table ?? static::sourceTable();
    }

    protected static function boot(): void
    {
        parent::boot();

        static::saving(static fn (self $model): bool => $model->syncing);
        static::deleting(static fn (self $model): bool => $model->syncing);
    }

    /** @param array<string, mixed> $attributes */
    public static function sync(int|string $key, array $attributes): void
    {
        $shadow = static::query()->withoutGlobalScopes()->find($key) ?? new static;
        $shadow->syncing = true;

        if (! static::shouldBeShadowed($attributes)) {
            $shadow->exists && $shadow->forceDelete();

            return;
        }

        $shadow->forceFill([...static::beforeSync($attributes, $shadow->exists ? $shadow : null), $shadow->getKeyName() => $key])->save();

        $shadow->syncing = false;
    }

    /**
     * Whether this copy keeps the announced row; a row it stops keeping leaves the copy.
     *
     * @param  array<string, mixed>  $attributes  the row as the source announced it
     */
    protected static function shouldBeShadowed(array $attributes): bool
    {
        return true;
    }

    /**
     * Derives what the copy needs and the source never announced; $previous is the copy as it
     * stood, so a transition can be dated.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected static function beforeSync(array $attributes, ?self $previous = null): array
    {
        return $attributes;
    }
}
