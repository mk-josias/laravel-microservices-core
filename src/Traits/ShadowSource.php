<?php

declare(strict_types=1);

namespace Microservices\Traits;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Microservices\Contracts\Colocation;
use Microservices\Contracts\Stream\Bus;
use Microservices\Events\ShadowChanged;
use Microservices\Exceptions\ServiceException;

/**
 * Put on a model other services keep a copy of. Every write announces the row as it now stands;
 * only the $shadowed fields travel, so a field left out never leaves its service.
 *
 * @property-read list<string> $shadowed
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @method static void saved(\Closure $callback)
 * @method static void deleted(\Closure $callback)
 *
 * @mixin Model
 */
trait ShadowSource
{
    public static function bootShadowSource(): void
    {
        static::saved(static function (self $model): void {
            if ($model->wasRecentlyCreated || $model->wasChanged($model->shadowedFields())) {
                $model->announceShadow();
            }
        });

        static::deleted(static fn (self $model) => $model->announceShadow(deleted: true));
    }

    /** The service owning the rows: the one the model's class belongs to. */
    public static function sourceService(): string
    {
        return app(Colocation::class)->serviceOf(static::class)
            ?? throw ServiceException::outsideService(static::class);
    }

    /**
     * A source row deleted for good still reaches the copies, as a soft delete of theirs.
     *
     * @param  list<string>  $keepers  the only services to update their copy; empty for all of them
     */
    public function announceShadow(bool $deleted = false, array $keepers = []): void
    {
        $attributes = $this->shadowedAttributes();

        if ($deleted && ($attributes['deleted_at'] ?? null) === null) {
            $attributes['deleted_at'] = now()->format('Y-m-d H:i:s');
        }

        app(Bus::class)->emit(new ShadowChanged(
            sourceService: static::sourceService(),
            source: $this->getTable(),
            key: $this->getKey(),
            attributes: $attributes,
            keepers: $keepers,
        ));
    }

    /**
     * Re-announces every row, soft-deleted ones included; a copy already in step is rewritten with itself.
     *
     * @param  list<string>  $keepers
     */
    public static function announceAll(int $chunk = 500, array $keepers = []): int
    {
        $announced = 0;

        app(Colocation::class)->within(static::sourceService(), static function () use ($chunk, $keepers, &$announced): void {
            static::query()->withoutGlobalScopes()->chunkById($chunk, function ($rows) use (&$announced, $keepers): void {
                foreach ($rows as $row) {
                    $row->announceShadow(keepers: $keepers);
                    $announced++;
                }
            });
        });

        return $announced;
    }

    /** @return list<string> */
    public function shadowedFields(): array
    {
        $dates = array_values(array_filter(
            ['created_at', 'updated_at', 'deleted_at'],
            fn (string $column): bool => array_key_exists($column, $this->getAttributes()),
        ));

        return [...$this->shadowed, ...$dates];
    }

    /** @return array<string, mixed> */
    private function shadowedAttributes(): array
    {
        $attributes = [];

        foreach ($this->shadowedFields() as $field) {
            $value = $this->getAttribute($field);

            $attributes[$field] = match (true) {
                $value instanceof BackedEnum => $value->value,
                $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
                default => $value,
            };
        }

        return $attributes;
    }
}
