<?php

declare(strict_types=1);

namespace Microservices\Services\Shadows;

use Illuminate\Database\Eloquent\Model;
use Microservices\Contracts\Colocation;
use Microservices\Contracts\Shadows\Shadowed;
use Microservices\Models\ShadowModel;

/** Finds, among the services this process runs, the copies each one declares and the source models it owns. */
class ShadowRegistry
{
    public function __construct(protected readonly Colocation $colocation) {}

    /** @return list<class-string<ShadowModel>> the copies of $sourceTable kept here, or by $keeper alone */
    public function shadowsOf(string $sourceTable, ?string $keeper = null): array
    {
        return array_values(array_filter(
            $this->localShadows($keeper),
            static fn (string $shadow): bool => $shadow::sourceTable() === $sourceTable,
        ));
    }

    /** @return list<class-string<ShadowModel>> every copy the local services keep, or $keeper alone */
    public function localShadows(?string $keeper = null): array
    {
        $shadows = [];

        foreach ($keeper === null ? $this->colocation->local() : [$keeper] as $service) {
            $shadows = [...$shadows, ...$this->kept($service)];
        }

        return $shadows;
    }

    /** @return list<string> the local services keeping a copy of $sourceTable */
    public function keepersOf(string $sourceTable): array
    {
        return array_values(array_filter(
            $this->colocation->local(),
            fn (string $service): bool => $this->shadowsOf($sourceTable, $service) !== [],
        ));
    }

    /** The local source model of $sourceTable, if this process runs its owner (or $owner is it). */
    public function sourceOf(string $sourceTable, ?string $owner = null): ?Shadowed
    {
        foreach ($owner === null ? $this->colocation->local() : [$owner] as $service) {
            foreach ($this->sources($service) as $class) {
                /** @var Model&Shadowed $model */
                $model = new $class;

                if ($model->getTable() === $sourceTable) {
                    return $model;
                }
            }
        }

        return null;
    }

    /** @return list<class-string> */
    public function scanSources(string $service): array
    {
        return microservices_classes_with(Shadowed::class, $this->colocation->classPath($service));
    }

    /** @return list<class-string<ShadowModel>> microservices.shadows: this service's list, or its entry when keyed by service */
    protected function kept(string $service): array
    {
        $declared = (array) config('microservices.shadows', []);

        /** @var list<class-string<ShadowModel>> */
        return array_values(array_is_list($declared)
            ? ($service === config('microservices.name') ? $declared : [])
            : (array) ($declared[$service] ?? []));
    }

    /** @return list<class-string> */
    protected function sources(string $service): array
    {
        return $this->scanSources($service);
    }
}
