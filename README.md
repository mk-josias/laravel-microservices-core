# Laravel Microservices

Laravel Microservices lets Laravel applications work together as microservices: each one owns its
data, and they talk only through signed RPC calls on shared contracts, ordered events with an
outbox, and read-only copies of each other's rows. It depends on nothing but Laravel.

- **Calls through contracts.** A service calls another through an interface it shares with it. The
  call is a signed HTTP request; no route to write on either side.
- **Events that are not lost.** An event can be written in the same transaction as the data, then
  published in order. A redelivered event is handled once.
- **Copies of another service's rows.** A service keeps the columns it needs from another service's
  table in its own database, kept up to date by events.
- **Any broker, any language.** Events travel through a driver installed next to the package:
  [Redis Streams](https://github.com/mk-josias/laravel-microservices/tree/main/packages/redis-event-stream),
  [Laravel queues](https://github.com/mk-josias/laravel-microservices/tree/main/packages/queue-event-stream),
  or your own, through one interface. The wire format is plain JSON, so a service in Node or Go can take part.

[laravel-distributable-modules](https://github.com/mk-josias/laravel-distributable-modules) builds on this package to run
several services as modules of one codebase. Whether a service is its own application or a module
is a deployment choice; the code that calls it, announces to it or copies its rows is the same.

## Installation

```bash
composer require mk-josias/laravel-microservices
composer require mk-josias/http-rpc             # the http driver of calls between services
composer require mk-josias/redis-event-stream   # an events driver, then MICROSERVICES_STREAM_DRIVER=redis
php artisan vendor:publish --tag=microservices-config
php artisan migrate   # the outbox tables: event_publications, event_consumptions
```

Name the application, and declare the services it talks to:

```php
// config/microservices.php
'name' => env('MICROSERVICES_NAME'),   // orders

'services' => [
    'billing' => ['host' => env('BILLING_HOST'), 'namespace' => 'Foundation\Billing'],
],
```

`host` is where a service answers RPC calls. `namespace` is where it shares its code with the
others, in the foundation.

## The foundation

Two applications that call each other through an interface both need that interface. It lives in
a Composer package every service requires, the foundation, with one folder per service:

```
foundation/                    your package, e.g. acme/foundation
└── src/
    ├── Billing/               Foundation\Billing\, what billing shares
    │   ├── Contracts/         the interfaces billing answers
    │   ├── Services/          their RpcService, used by the callers
    │   ├── Payloads/          the versions of billing's events, once one changes shape
    │   └── Shadows/           the copies other services may keep of billing's tables
    └── Orders/
billing/   orders/             the applications: each requires the foundation
```

```bash
php artisan foundation:make ../foundation --package=acme/foundation   # in billing: creates it, adds src/Billing
php artisan foundation:make ../foundation                             # in orders: adds src/Orders
```

A service's implementation, models and migrations stay in its application. A new version of the
foundation reaches each service when it updates it; versioned payloads let them do it one at a time.

## Calling another service

The service that answers owns a contract. The caller gets an `RpcService` that implements it:

```php
// Foundation\Billing\Contracts\BillingService, in the foundation
interface BillingService
{
    public function invoiceFor(int $orderId): ?array;
}

// Foundation\Billing\Services\BillingRpcService, in the foundation, used by the callers
final class BillingRpcService extends \Microservices\Services\Rpc\RpcService implements BillingService
{
    public function invoiceFor(int $orderId): ?array
    {
        return $this->call('invoiceFor', ['orderId' => $orderId]);
    }
}

// app/Providers/ServicesProvider.php, in orders
final class ServicesProvider extends \Microservices\Providers\RpcServiceProvider
{
    protected array $rpc = [BillingService::class => BillingRpcService::class];         // the contracts orders calls
    protected array $services = [OrdersService::class => \App\Services\OrdersService::class];  // the ones it answers
}
```

`app(BillingService::class)->invoiceFor(7)` sends `POST {billing host}/billing/rpc/invoiceFor`,
signed with the shared secret. Billing answers on that route, which the package serves for every
contract in its `$services`: no route to write. See [RPC](docs/rpc.md).

## Announcing and handling events

```php
final class OrderPlaced extends \Microservices\Events\Event
{
    public function __construct(private readonly int $id) {}

    public function name(): string { return 'orders.order.placed'; }

    public function payload(): array { return ['id' => $this->id]; }

    public function version(): int { return 1; }   // raised when the shape of the payload changes
}

app(\Microservices\Contracts\Stream\Bus::class)->emit(new OrderPlaced($order->id));
```

The consuming service lists its handlers and runs a consumer:

```php
// config/microservices.php, in billing
'events' => ['listen' => ['orders.order.placed' => [\App\Handlers\OpenInvoice::class]]],
```

```bash
php artisan stream:consume
```

See [Events](docs/events.md) for the outbox, versions, streams and transports.

## Keeping a copy of another service's rows

A service that needs to join, filter or sort on another service's rows keeps the columns it needs
in its own database. Only the owner writes them: the copy follows its events and refuses any other write.

```php
// in billing: the source, announced on every write
final class Customer extends Model implements \Microservices\Contracts\Shadows\Shadowed
{
    use \Microservices\Traits\ShadowSource;

    protected array $shadowed = ['name'];
}

// in the foundation: the copy as billing describes it
namespace Foundation\Billing\Shadows;

class CustomerShadow extends \Microservices\Models\ShadowModel
{
    public static function owner(): string { return 'billing'; }

    public static function sourceTable(): string { return 'customers'; }
}

// in orders, config/microservices.php: the copies it keeps
'shadows' => [\Foundation\Billing\Shadows\CustomerShadow::class],

// in orders: the migration of that table
return new class extends \Microservices\Migrations\ShadowMigration
{
    protected function source(): string { return 'customers'; }

    public function up(): void
    {
        Schema::create($this->table(), function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();   // the source's key
            $table->string('name');
            $table->softDeletes();
        });
    }
};
```

A copy created after the source had data is filled with `shadows:want`. See [Copies](docs/shadows.md).

## Documentation

| Page | |
|---|---|
| [RPC](docs/rpc.md) | contracts, `RpcService`, signature, cache, transports |
| [Events](docs/events.md) | envelope, outbox, versions, streams, transports, consumers, archive |
| [Writing a transport](docs/transports.md) | what an events driver has to guarantee, and what each feature needs from it |
| [Copies](docs/shadows.md) | `ShadowSource`, `ShadowModel`, filling a new copy |
| [Services in other languages](docs/other-languages.md) | what a Node or Go service has to speak |
| [Configuration](docs/configuration.md) | every key, and how to run several services in one process |
| [Example](https://github.com/mk-josias/laravel-microservices/tree/main/examples) | iam, notifications and analytics as three applications, with their foundation and a Node gateway, run in CI |

## Extending

| To | Implement | Register with |
|---|---|---|
| carry events on Kafka, RabbitMQ, another package | `Contracts\Stream\Transport` | `Services\Stream\TransportManager::extend()` |
| carry calls on gRPC, an existing REST API | `Contracts\Rpc\Transport` | `Services\Rpc\TransportManager::extend()` |
| keep exported events on S3, in a database | `Contracts\Stream\ArchiveStore` | `Services\Stream\Outbox\ArchiveStores::extend()` |
| run several services in one process | `Contracts\Colocation` | the container (laravel-distributable-modules does) |
| emit an object that already extends another class | `Contracts\Stream\Event` (`Events\Event` implements it with defaults) | nothing: `Bus::emit()` takes the interface |
| emit differently (another outbox, a log of every emission) | `Contracts\Stream\Bus` | the container |

## License

MIT
