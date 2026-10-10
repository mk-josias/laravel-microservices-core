<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Microservices\Exceptions\ServiceException;
use Microservices\Providers\MicroservicesServiceProvider;
use Microservices\Services\Rpc\LocalServices;
use Microservices\Services\Rpc\RpcSignature;
use Microservices\Tests\Fixtures\App\Contracts\OrdersService;
use Microservices\Tests\Fixtures\App\Models\Order;
use Microservices\Tests\Fixtures\Billing\Contracts\BillingService;
use Microservices\Tests\Fixtures\Billing\Services\BillingRpcService;

/**
 * @param  array<string, mixed>  $arguments
 * @return array{0: array<string, mixed>, 1: array<string, string>}
 */
function signedCall(string $path, string $contract, array $arguments = [], array $context = []): array
{
    $body = ['contract' => $contract, 'arguments' => (object) $arguments];

    return [$body, app(RpcSignature::class)->headers($path, json_encode($body), json_encode((object) $context))];
}

it('calls a service running elsewhere over signed HTTP, on the route named after it', function () {
    Http::fake(['billing.test/*' => Http::response(['order_id' => 7, 'amount' => 120])]);

    expect(app(BillingService::class))->toBeInstanceOf(BillingRpcService::class)
        ->and(app(BillingService::class)->invoiceFor(7))->toBe(['order_id' => 7, 'amount' => 120]);

    Http::assertSent(function (ClientRequest $request): bool {
        $signature = app(RpcSignature::class)->sign(
            $request->header(RpcSignature::TIMESTAMP_HEADER)[0],
            $request->header(RpcSignature::NONCE_HEADER)[0],
            '/billing/rpc/invoiceFor',
            $request->body(),
            $request->header(RpcSignature::CONTEXT_HEADER)[0],
        );

        return $request->url() === 'http://billing.test/billing/rpc/invoiceFor'
            && $request['contract'] === BillingService::class
            && $request['arguments'] === ['orderId' => 7]
            && $request->header(RpcSignature::SIGNATURE_HEADER)[0] === $signature;
    });
});

it('reads a 404 from the other service as null', function () {
    Http::fake(['billing.test/*' => Http::response(null, 404)]);

    expect(app(BillingService::class)->invoiceFor(7))->toBeNull();
});

it('answers its own contract on a route named after this service', function () {
    $order = Order::query()->create(['total' => 120]);
    [$body, $headers] = signedCall('/orders/rpc/find', OrdersService::class, ['id' => $order->id]);

    $this->postJson('/orders/rpc/find', $body, $headers)->assertOk()->assertExactJson(['id' => $order->id, 'total' => 120]);
});

it('answers null with a 404, an unknown method with a 400, an unsigned call with a 403', function () {
    [$body, $headers] = signedCall('/orders/rpc/find', OrdersService::class, ['id' => 404]);
    $this->postJson('/orders/rpc/find', $body, $headers)->assertNotFound();

    [$body, $headers] = signedCall('/orders/rpc/cancel', OrdersService::class);
    $this->postJson('/orders/rpc/cancel', $body, $headers)->assertStatus(400)
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'Service [orders] answers no'));

    $this->postJson('/orders/rpc/find', ['contract' => OrdersService::class, 'arguments' => ['id' => 1]])->assertForbidden();
});

it('accepts a signed call once', function () {
    [$body, $headers] = signedCall('/orders/rpc/find', OrdersService::class, ['id' => 1]);

    $this->postJson('/orders/rpc/find', $body, $headers)->assertNotFound();
    $this->postJson('/orders/rpc/find', $body, $headers)->assertForbidden();
});

it('restores the propagated context on the called side', function () {
    [$body, $headers] = signedCall('/orders/rpc/traceId', OrdersService::class, [], ['trace_id' => 'abc']);

    $this->postJson('/orders/rpc/traceId', $body, $headers)->assertOk()->assertContent('"abc"');
});

it('calls and answers on the path microservices.rpc.path names, in the group it names', function () {
    config()->set('microservices.rpc.path', 'internal/{service}/{method}');
    config()->set('microservices.rpc.middleware_group', 'internal-rpc');
    (new MicroservicesServiceProvider(app()))->boot();
    Http::fake(['billing.test/*' => Http::response(['order_id' => 7])]);

    app(BillingService::class)->invoiceFor(7);
    $order = Order::query()->create(['total' => 120]);
    [$body, $headers] = signedCall('/internal/orders/find', OrdersService::class, ['id' => $order->id]);

    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'http://billing.test/internal/billing/invoiceFor');
    $this->postJson('/internal/orders/find', $body, $headers)->assertOk();
    $this->postJson('/internal/orders/find', ['contract' => OrdersService::class, 'arguments' => ['id' => 1]])->assertForbidden();
});

it('refuses a method of a contract the service does not answer', function () {
    app(LocalServices::class)->call('orders', BillingService::class, 'invoiceFor', ['orderId' => 1]);
})->throws(ServiceException::class, 'Service [orders] answers no');
