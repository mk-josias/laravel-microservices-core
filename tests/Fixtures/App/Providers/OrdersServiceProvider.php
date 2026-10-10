<?php

declare(strict_types=1);

namespace Microservices\Tests\Fixtures\App\Providers;

use Microservices\Providers\RpcServiceProvider;
use Microservices\Tests\Fixtures\App\Contracts\OrdersService as OrdersContract;
use Microservices\Tests\Fixtures\App\Services\OrdersService;
use Microservices\Tests\Fixtures\Billing\Contracts\BillingService;
use Microservices\Tests\Fixtures\Billing\Services\BillingRpcService;

final class OrdersServiceProvider extends RpcServiceProvider
{
    protected array $rpc = [
        BillingService::class => BillingRpcService::class,
    ];

    protected array $services = [
        OrdersContract::class => OrdersService::class,
    ];
}
