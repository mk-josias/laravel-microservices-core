<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Microservices\Contracts\Stream\Transport;
use Microservices\Data\Envelope;
use Microservices\Events\ShadowChanged;
use Microservices\Services\Stream\Dispatcher;
use Microservices\Tests\Fixtures\App\Models\CustomerShadow;
use Microservices\Tests\Fixtures\App\Models\Order;

function customerChanged(int $key, array $attributes): Envelope
{
    return new Envelope((string) str()->uuid7(), 'billing', ShadowChanged::NAME, ['source' => 'customers', 'key' => $key, 'attributes' => $attributes], [], now()->toImmutable());
}

it('keeps a copy of the rows another service announces, in a table named after this service', function () {
    app(Dispatcher::class)->dispatch(customerChanged(3, ['name' => 'Ada']), 'orders');
    app(Dispatcher::class)->dispatch(customerChanged(3, ['name' => 'Grace']), 'orders');

    expect((new CustomerShadow)->getTable())->toBe('customers')
        ->and(DB::table('customers')->get()->map(fn ($row): array => (array) $row)->all())
        ->toBe([['id' => 3, 'name' => 'Grace', 'deleted_at' => null]]);
});

it('soft-deletes the copy of a row deleted at the source', function () {
    app(Dispatcher::class)->dispatch(customerChanged(3, ['name' => 'Ada']), 'orders');
    app(Dispatcher::class)->dispatch(customerChanged(3, ['name' => 'Ada', 'deleted_at' => '2026-10-03 10:00:00']), 'orders');

    expect(CustomerShadow::query()->count())->toBe(0)
        ->and(CustomerShadow::query()->withTrashed()->count())->toBe(1);
});

it('keeps only the rows its copy wants, and drops a row that stops matching', function () {
    app(Dispatcher::class)->dispatch(customerChanged(3, ['name' => 'Ada']), 'orders');
    app(Dispatcher::class)->dispatch(customerChanged(4, ['name' => 'Hidden']), 'orders');
    app(Dispatcher::class)->dispatch(customerChanged(3, ['name' => 'Hidden']), 'orders');

    expect(CustomerShadow::query()->withTrashed()->count())->toBe(0);
});

it('never writes the copy outside a sync', function () {
    expect((new CustomerShadow(['id' => 1, 'name' => 'Ada']))->save())->toBeFalse();
});

it('announces a source row with only its shadowed fields, as this service', function () {
    $order = Order::query()->create(['total' => 120, 'note' => 'private']);

    $envelope = app(Transport::class)->published()[0];

    expect($envelope->emitter)->toBe('orders')
        ->and($envelope->name)->toBe(ShadowChanged::NAME)
        ->and($envelope->payload)->toBe(['source' => 'orders', 'key' => $order->id, 'attributes' => ['total' => 120]]);
});
