<?php

declare(strict_types=1);

namespace Microservices\Tests\Fixtures\Billing\Services;

use Microservices\Services\Rpc\RpcService;
use Microservices\Tests\Fixtures\Billing\Contracts\BillingService;

final class BillingRpcService extends RpcService implements BillingService
{
    public function invoiceFor(int $orderId): ?array
    {
        /** @var array{order_id: int, amount: int}|null */
        return $this->call('invoiceFor', ['orderId' => $orderId]);
    }
}
