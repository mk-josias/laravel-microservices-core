<?php

declare(strict_types=1);

namespace Microservices\Tests\Fixtures\App\Events;

use Microservices\Events\Event;

final class OrderPlaced extends Event
{
    public function __construct(private readonly int $id) {}

    public function name(): string
    {
        return 'orders.order.placed';
    }

    public function payload(): array
    {
        return ['id' => $this->id];
    }
}
