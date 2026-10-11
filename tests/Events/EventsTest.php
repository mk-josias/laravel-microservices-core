<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Microservices\Contracts\Stream\Bus;
use Microservices\Contracts\Stream\Transport;
use Microservices\Data\Envelope;
use Microservices\Exceptions\ConfigurationException;
use Microservices\Providers\MicroservicesServiceProvider;
use Microservices\Services\Stream\Dispatcher;
use Microservices\Tests\Fixtures\App\Events\OrderPlaced;
use Microservices\Tests\Fixtures\App\Handlers\OnInvoicePaid;

it('stamps an event with this service as its emitter, and the propagated context', function () {
    config()->set('microservices.events.propagate', ['trace_id']);
    Context::add('trace_id', 'abc');

    app(Bus::class)->emit(new OrderPlaced(7));

    $envelope = app(Transport::class)->published()[0];

    expect($envelope)->toBeInstanceOf(Envelope::class)
        ->and($envelope->emitter)->toBe('orders')
        ->and($envelope->name)->toBe('orders.order.placed')
        ->and($envelope->payload)->toBe(['id' => 7])
        ->and($envelope->headers)->toBe(['trace_id' => 'abc']);
});

it('runs the handlers listening to an event another service emitted', function () {
    config()->set('microservices.events.listen', ['billing.invoice.paid' => [OnInvoicePaid::class]]);

    $this->receive('billing.invoice.paid', ['order_id' => 7]);

    expect(DB::table('paid_orders')->pluck('order_id')->all())->toBe([7]);
});

it('writes the event in the outbox with the business transaction, then publishes it', function () {
    config()->set('microservices.events.streams.default.outbox', true);

    DB::transaction(fn () => app(Bus::class)->emit(new OrderPlaced(7)));

    expect(app(Transport::class)->published())->toBe([])
        ->and(DB::table('event_publications')->where('emitter', 'orders')->whereNull('published_at')->count())->toBe(1);

    $this->artisan('stream:publish', ['--once' => true])->assertSuccessful();

    expect(app(Transport::class)->published()[0]->name)->toBe('orders.order.placed')
        ->and(DB::table('event_publications')->whereNull('published_at')->count())->toBe(0);
});

it('keeps the outbox and the consumption marks in the tables microservices.events.tables names', function () {
    config()->set('microservices.events.tables', ['publications' => 'outbox', 'consumptions' => 'inbox']);
    $migrations = dirname((string) (new ReflectionClass(MicroservicesServiceProvider::class))->getFileName(), 3).'/database/migrations';
    foreach (['0001_01_01_000000_create_event_publications_table', '0001_01_01_000001_create_event_consumptions_table', '0001_01_01_000002_add_version_to_event_publications_table'] as $file) {
        (require "{$migrations}/{$file}.php")->up();
    }
    config()->set('microservices.events.streams.default.outbox', true);
    config()->set('microservices.events.guard', true);
    config()->set('microservices.events.listen', ['billing.invoice.paid' => [OnInvoicePaid::class]]);

    app(Bus::class)->emit(new OrderPlaced(7));
    app(Dispatcher::class)->dispatch(new Envelope('0191f3c2-8a41-7c2e-9b55-3f1c7d0a4e90', 'billing', 'billing.invoice.paid', ['order_id' => 7], [], now()->toImmutable()), 'orders');

    expect(DB::table('outbox')->count())->toBe(1)
        ->and(DB::table('inbox')->count())->toBe(1)
        ->and(DB::table('event_publications')->count())->toBe(0);
});

it('handles a redelivered event once', function () {
    config()->set('microservices.events.guard', true);
    config()->set('microservices.events.listen', ['billing.invoice.paid' => [OnInvoicePaid::class]]);

    $envelope = new Envelope('0191f3c2-8a41-7c2e-9b55-3f1c7d0a4e88', 'billing', 'billing.invoice.paid', ['order_id' => 7], [], now()->toImmutable());

    app(Dispatcher::class)->dispatch($envelope, 'orders');
    app(Dispatcher::class)->dispatch($envelope, 'orders');

    expect(DB::table('paid_orders')->count())->toBe(1)
        ->and(DB::table('event_consumptions')->count())->toBe(1);
});

it('names the package of a shipped driver that is not installed', function () {
    config()->set('microservices.events.streams.default.driver', 'redis');

    expect(fn () => app(Transport::class))->toThrow(ConfigurationException::class, 'composer require mk-josias/laravel-microservices-redis-stream');
});

it('consumes the stream as this service', function () {
    config()->set('microservices.events.listen', ['billing.invoice.paid' => [OnInvoicePaid::class]]);
    app(Transport::class)->publish(new Envelope('0191f3c2-8a41-7c2e-9b55-3f1c7d0a4e89', 'billing', 'billing.invoice.paid', ['order_id' => 9], [], now()->toImmutable()));

    $this->artisan('stream:consume')->expectsOutputToContain('Consuming as [orders] from: orders, billing')->assertSuccessful();

    expect(DB::table('paid_orders')->pluck('order_id')->all())->toBe([9]);
});
