<?php

declare(strict_types=1);

namespace Microservices\Contracts\Rpc;

/** Carries a call to a method of a contract, answered by the service that implements it. */
interface Transport
{
    /**
     * @param  class-string  $contract
     * @param  array<string, mixed>  $arguments  the method's arguments, by name
     * @return mixed the answer decoded as JSON would decode it; null when there is none
     */
    public function invoke(string $service, string $contract, string $method, array $arguments = []): mixed;
}
