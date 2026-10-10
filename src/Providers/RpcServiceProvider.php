<?php

declare(strict_types=1);

namespace Microservices\Providers;

use Illuminate\Support\ServiceProvider;
use Microservices\Config\Services;
use Microservices\Contracts\Colocation;
use Microservices\Contracts\Stream\Versioned;
use Microservices\Services\Rpc\LocalServices;
use Microservices\Services\Rpc\RpcService;
use Microservices\Services\Rpc\RpcServices;
use Microservices\Services\Stream\PayloadVersions;

/**
 * Base of the provider that wires an application's contracts: the ones it calls, each bound to
 * the RpcService that reaches its service, and the ones it answers, each with its implementation.
 */
abstract class RpcServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string<RpcService>> each contract this application calls, with its RpcService */
    protected array $rpc = [];

    /** @var array<class-string, class-string> each contract this application answers, with its implementation */
    protected array $services = [];

    /** @var array<string, class-string<Versioned>> event name => the payload class that declares its versions */
    protected array $payloads = [];

    public function boot(): void
    {
        $name = $this->app->make(Services::class)->getName();

        foreach ($this->services as $contract => $implementation) {
            if ($name !== null) {
                $this->app->make(LocalServices::class)->add($name, $contract, $implementation);
            }

            $this->app->bind($contract, $implementation);
        }

        $this->app->make(PayloadVersions::class)->add($this->payloads);

        $colocation = $this->app->make(Colocation::class);
        $services = [];

        foreach ($this->rpc as $contract => $service) {
            $this->app->bind($contract, $service);

            $services[$contract] = ['service' => $colocation->serviceOf($contract), 'rpc' => $service];
        }

        $this->app->make(RpcServices::class)->add($services);
    }
}
