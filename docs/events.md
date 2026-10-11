# Events

## How an event travels

Events are asynchronous: a service announces that something happened, and the services that care
handle it later, in their own consumer process.

```
emitting service                         stream                    consuming service
emit(Event) ─► Envelope ─► [outbox ─► publisher] ─► transport ─► stream:consume
                           (table)    (process)     (redis …)     └► Dispatcher ─► handlers
```

The consuming service declares its handlers:

```php
// config/microservices.php
'events' => ['listen' => ['orders.order.placed' => [OpenInvoice::class]]],
```

A handler implements `Microservices\Contracts\Stream\Handler` and receives the event name and the
raw payload.

## The envelope

```json
{
  "id":         "0191f3c2-8a41-7c2e-9b55-3f1c7d0a4e88",
  "emitter":    "orders",
  "name":       "orders.order.placed",
  "payload":    { "id": 42 },
  "headers":    { "trace_id": "4bf92f35" },
  "emitted_at": "2026-09-30T13:22:41.512000Z",
  "recipients": [],
  "stream":     "default",
  "version":    1
}
```

`emitter` is the service the event class belongs to: `microservices.name` in an application on its
own. Override `Event::emitter()` to set it yourself.

`recipients` is optional. When it's empty, every service can handle the event. When you fill it by
overriding `Event::recipients()`, only the listed services handle it. The other consumers
acknowledge it and skip it, and the `queue` transport doesn't deliver it to them at all.

## Versioning a payload

A stream keeps events for a long time, and a consumer may be deployed after the emitter. `version`
is the version of the payload's shape. It stays at 1 as long as you only add optional fields.
Renaming a field, removing one or changing what one means raises it.

The emitter declares its versions on a payload class, which consumers can read too:

```php
final class OrderPlacedPayload implements \Microservices\Contracts\Stream\Versioned
{
    public static function version(): int { return 2; }

    public static function upcast(int $from, array $payload): array
    {
        return match ($from) {
            1 => ['id' => $payload['id'], 'total' => $payload['amount']],   // 2 renamed amount
            default => $payload,
        };
    }
}

// in a RpcServiceProvider
protected array $payloads = ['orders.order.placed' => OrderPlacedPayload::class];
```

Before a handler runs, the payload is lifted one version at a time up to the version the process
reads. A handler therefore only ever sees the current shape, including for events written before
the change.

| The envelope's version | What happens |
|---|---|
| the version the process reads | the handler gets the payload as it is |
| older | `upcast()` runs once per missing version, then the handler |
| newer: the emitter was deployed before this consumer | `ServiceException`, no handler runs. On the `redis` transport, `on_failure: block` makes the stream wait for the consumer's deployment; with `skip` the later entries go on and this one comes back after `claim_after` |
| above 1 for an event with no entry in `$payloads` | the same `ServiceException` |

## Context propagation

```php
'events' => ['propagate' => ['trace_id', 'locale']],
```

These keys of Laravel's `Context` are copied into the envelope headers when the event is emitted,
and restored around each handler. RPC calls carry them too.

## Transports

| Driver | Comes with | |
|---|---|---|
| `redis` | [`mk-josias/laravel-microservices-redis-stream`](https://github.com/mk-josias/laravel-microservices/tree/main/packages/redis-stream) | Redis Streams, for the `default` stream once `MICROSERVICES_STREAM_DRIVER=redis`. One Redis stream per configured stream (`microservices:events`), written by every service, and one consumer group per consuming service. Entries are handled in the order they were published. With `'on_failure' => 'block'` (the default), a failing entry blocks the ones after it and is retried first. With `'skip'`, the ones after it go on, and the failed entry comes back after `claim_after`. |
| `queue` | [`mk-josias/laravel-microservices-queue-stream`](https://github.com/mk-josias/laravel-microservices/tree/main/packages/queue-stream) | Any Laravel queue connection (`database`, `sqs`, …), if you don't use Redis. Each envelope is copied to one queue per declared service (`microservices:events-{service}`). A failed envelope is retried after the ones behind it, so order isn't kept after a failure. |
| `array` | this package | In memory, for tests. Consuming reads everything and returns. |
| `null` | this package | Drops everything. |

A stream is an entry in `microservices.events.streams` with a driver and its options, like a queue
connection. An event chooses its stream with `stream()`; `null` means `microservices.events.stream`.
Order is only kept within a stream.

You can add your own driver; [Writing a transport](transports.md) lists what it has to guarantee:

```php
app(\Microservices\Services\Stream\TransportManager::class)
    ->extend('kafka', fn ($app, array $options, string $stream) => new KafkaTransport($options));
```

A transport implements `publish()`, a blocking `consume()` loop and `stop()`. If the consume
callback returns normally, the message is acknowledged; if it throws, it isn't. The other commands
ask the transport for more, through these interfaces:

| Interface | Used by | Without it |
|---|---|---|
| `Contracts\Stream\TrimsStreams` | `stream:trim` | the command trims nothing |
| `Contracts\Stream\TracksAcknowledgements` | `stream:export --acknowledged`, and the publisher, which records the id of each entry | `--acknowledged` fails; publishing works |
| `Contracts\Stream\RedeliversEnvelopes` | the consumption guard, turned on when delivery is at-least-once | the guard stays off unless the stream has an outbox |

Redis keeps every entry until every consumer group has acknowledged it. Nothing is trimmed when
writing, so a stopped consumer or a service added later doesn't miss anything. Run the trim command
on a schedule:

```bash
php artisan stream:trim [--stream=default]
```

## The outbox

On a stream with `'outbox' => true` (`MICROSERVICES_STREAM_OUTBOX=true` for the `default` stream),
`emit()` writes a row to `event_publications` in the emitting service's database, inside the
current transaction. The data and the event are committed or rolled back together. A publisher
process sends the rows to the stream:

```bash
php artisan stream:publish [--service=*] [--batch=100] [--sleep=1] [--once]
```

Rows are published in `sequence` order, and a failing row stops the run. This only works with one
publisher per service, so don't run two.

```bash
php artisan stream:republish [--service=*] [--since=2026-09-01] [--force]    # rebuild an emptied stream
php artisan stream:export storage/events.jsonl [--store=file] [--service=*] [--stream=default] \
    [--since=2026-08-01] [--until=2026-09-01] [--where=name=orders.order.placed] [--where=payload.status=paid] \
    [--acknowledged] [--batch=1000]
php artisan stream:import storage/events.jsonl [--store=file] [--service=*] [--stream=default] \
    [--since=2026-08-01] [--until=2026-09-01] [--where=payload.status=paid] [--batch=1000]
```

`export` moves published rows to an archive; `import` puts the matching ones back as pending
publications, in the order they were written. Both take the same filters. `--acknowledged` only
exports what every consumer has read, and needs a transport implementing `TracksAcknowledgements`.

The archive is a store: `file` (JSON lines, one row per line) ships, and
`microservices.events.archive` names the default. Another one implements
`Contracts\Stream\ArchiveStore` (`write($target, $rows)`, `read($source)`) and is registered by name:

```php
app(\Microservices\Services\Stream\Outbox\ArchiveStores::class)
    ->extend('s3', fn ($app) => new S3ArchiveStore($app->make('filesystem')->disk('s3')));
```

## Consuming

```bash
php artisan stream:consume [--service=billing] [--stream=default]
```

The command reads one stream as the consuming service, for every declared emitter on it. An entry
whose emitter isn't a declared service is acknowledged unread. Run one process per stream, like
`queue:work`. On `SIGTERM` it finishes the current message and stops.

In a test, `Microservices\Testing\InteractsWithServices::receive('orders.order.placed', [...])`
hands the event to the handlers, without a stream or a consumer.

## Idempotent handlers

When delivery is at-least-once, each handler run is guarded: an `(event_id, handler)` row is
inserted into `event_consumptions` in the same transaction as the handler's writes. If the event is
delivered again, the row is already there and the handler doesn't run. If the handler throws, the
row is rolled back and the event is retried. The guard is enabled automatically with the outbox or
with a transport that implements `Contracts\Stream\RedeliversEnvelopes` (`redis` and `queue` do).
A handler that is already idempotent can implement `Contracts\Stream\Idempotent` to skip it.

The transaction stays open for the whole handler, so a guarded handler calls other services before
it writes: its rows are not locked while it waits on the network.
