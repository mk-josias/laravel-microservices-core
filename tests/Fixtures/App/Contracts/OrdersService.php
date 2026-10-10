<?php

declare(strict_types=1);

namespace Microservices\Tests\Fixtures\App\Contracts;

interface OrdersService
{
    /** @return array{id: int, total: int}|null */
    public function find(int $id): ?array;

    public function traceId(): ?string;
}
