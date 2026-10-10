<?php

declare(strict_types=1);

namespace Microservices\Tests\Fixtures\App\Services;

use Illuminate\Support\Facades\Context;
use Microservices\Tests\Fixtures\App\Models\Order;

final class OrdersService implements \Microservices\Tests\Fixtures\App\Contracts\OrdersService
{
    public function find(int $id): ?array
    {
        $order = Order::query()->find($id);

        return $order === null ? null : ['id' => $order->id, 'total' => $order->total];
    }

    public function traceId(): ?string
    {
        $trace = Context::get('trace_id');

        return is_string($trace) ? $trace : null;
    }
}
