<?php

declare(strict_types=1);

namespace Microservices\Tests\Fixtures\Billing\Contracts;

/** The contract billing answers; orders has it to call billing. */
interface BillingService
{
    /** @return array{order_id: int, amount: int}|null */
    public function invoiceFor(int $orderId): ?array;
}
