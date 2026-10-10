<?php

declare(strict_types=1);

namespace Microservices\Tests\Fixtures\App\Handlers;

use Illuminate\Support\Facades\DB;
use Microservices\Contracts\Stream\Handler;

/** Each run leaves a row, so a replay the guard let through would show. */
final class OnInvoicePaid implements Handler
{
    public function handle(string $name, array $payload): void
    {
        DB::table('paid_orders')->insert(['order_id' => $payload['order_id']]);
    }
}
