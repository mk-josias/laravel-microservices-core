<?php

declare(strict_types=1);

namespace Microservices\Contracts;

use Closure;

/**
 * Which services this process runs, and what each one owns in it. An application is one service
 * (SingleService); a package that runs several services in one process binds its own.
 */
interface Colocation
{
    /** @return list<string> the services this process runs */
    public function local(): array;

    /** The service a class belongs to: its own code, or the contract and RpcService of a service it calls. */
    public function serviceOf(string $class): ?string;

    /** The service the running code belongs to. */
    public function current(): ?string;

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function within(?string $service, Closure $callback): mixed;

    /** The database connection of a local service; null for the default one. */
    public function connection(string $service): ?string;

    /** The directory where a local service's classes live, searched for its copies and their sources. */
    public function classPath(string $service): string;
}
