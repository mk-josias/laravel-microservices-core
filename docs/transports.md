# Writing a transport

An events driver implements `Microservices\Contracts\Stream\Transport` and is registered by name:

```php
app(\Microservices\Services\Stream\TransportManager::class)
    ->extend('kafka', fn ($app, array $options, string $stream) => new KafkaTransport($options));
```

[redis-event-stream](https://github.com/mk-josias/laravel-microservices/tree/main/packages/redis-event-stream) and
[queue-event-stream](https://github.com/mk-josias/laravel-microservices/tree/main/packages/queue-event-stream) are built this way: a provider that calls
`extend()`, and nothing else in the package knows them.

## The contract

| Method | Has to |
|---|---|
| `publish(Envelope $envelope)` | put the envelope on the stream, as `Envelope::toJson()` writes it, so services in other languages and other drivers read the same thing |
| `consume(string $consumer, array $channels, callable $handle)` | loop until `stop()`, as `$consumer`'s own cursor, handing `$handle` the envelopes whose emitter is in `$channels`; acknowledge an envelope only when `$handle` returns, never when it throws |
| `stop()` | let the current envelope finish, then return from `consume()`: it is what a `SIGTERM` calls |

## What the package relies on

The transport decides what an application gets. The package does not check it: it documents it.

| Guarantee | What depends on it | Without it |
|---|---|---|
| **Order within a stream**, including after a failure | a consumer that reads a copy filled by an earlier event (a welcome mail reading the user's copy); the publisher, which sends each service's outbox in `sequence` order | an event can overtake the one it depends on: declare it, so applications put such events on a transport that keeps order |
| **At least once**: an unacknowledged envelope comes back | nothing is lost when a handler or a process fails | an event can be lost |
| **One cursor per consumer** | a new consumer disturbs none of the others | consumers steal each other's envelopes |

Say which ones yours keeps, as the drivers shipped here do in their README.

## What each feature asks for

| Interface | Used by | Without it |
|---|---|---|
| `RedeliversEnvelopes` | the consumption guard turns itself on (`Dispatcher::guarded()`), so a redelivered envelope runs its handlers once | the guard stays off unless the stream has an outbox, or `microservices.events.guard` is set |
| `TracksAcknowledgements` | the publisher records the stream id of each row; `stream:export --acknowledged` | `--acknowledged` fails; publishing works |
| `TrimsStreams` | `stream:trim` | the command says there is nothing to trim |
