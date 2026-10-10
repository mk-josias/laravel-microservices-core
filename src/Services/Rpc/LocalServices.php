<?php

declare(strict_types=1);

namespace Microservices\Services\Rpc;

use Illuminate\Container\Container;
use Microservices\Contracts\Colocation;
use Microservices\Exceptions\ServiceException;

/**
 * The implementation each local service gives its contracts (a provider's $services). Every
 * transport ends here, so a call behaves the same whether it came from this process or another.
 */
final class LocalServices
{
    /** @var array<class-string, array{service: string, implementation: class-string}> */
    private array $services = [];

    /**
     * @param  class-string  $contract
     * @param  class-string  $implementation
     */
    public function add(string $service, string $contract, string $implementation): void
    {
        $this->services[$contract] = ['service' => $service, 'implementation' => $implementation];
    }

    /** Only a method of a contract the service declares in $services can be called. */
    public function answers(string $service, string $contract, string $method): bool
    {
        return ($this->services[$contract]['service'] ?? null) === $service && method_exists($contract, $method);
    }

    /**
     * Runs a method of $contract in its service's context and returns the answer as JSON decodes it,
     * the shape a remote caller receives.
     *
     * @param  class-string  $contract
     * @param  array<string, mixed>  $arguments
     */
    public function call(string $service, string $contract, string $method, array $arguments = []): mixed
    {
        if (! $this->answers($service, $contract, $method)) {
            throw ServiceException::unknownRpcMethod($service, $contract, $method);
        }

        $implementation = $this->services[$contract]['implementation'];

        $answer = Container::getInstance()->make(Colocation::class)->within(
            $service,
            static fn (): mixed => Container::getInstance()->make($implementation)->{$method}(...$arguments),
        );

        return json_decode(json_encode($answer, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
    }
}
