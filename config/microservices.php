<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | This service and the others
    |--------------------------------------------------------------------------
    | `name` is this application's name among the services: the emitter of its
    | events and the first segment of its RPC routes. Every other service is
    | declared by name: `host` is where it answers, a URL or ['url' => …,
    | 'transport' => …]; `namespace` is where its contracts and its RpcService
    | live in this codebase, so a call knows which service it goes to.
    */

    'name' => env('MICROSERVICES_NAME'),

    'services' => [
        // 'billing' => ['host' => env('BILLING_HOST'), 'namespace' => 'Billing'],
    ],

    // The copies this service keeps of other services' tables (ShadowModel classes), or service => copies.
    'shadows' => [
        // \Billing\Shadows\CustomerShadow::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Calls between services
    |--------------------------------------------------------------------------
    | Named transports, like queue connections; a driver other than `http`
    | comes from Services\Rpc\TransportManager::extend(). A service's host may name the
    | one its calls travel on.
    */

    'rpc' => [
        'transport' => env('MICROSERVICES_RPC_TRANSPORT', 'http'),

        'transports' => [
            'http' => ['driver' => 'http'],
        ],

        // Where a service answers a call over http, the same in every service, and the middleware group of those routes.
        'path' => env('MICROSERVICES_RPC_PATH', '{service}/rpc/{method}'),
        'middleware_group' => 'rpc',

        // Signs every call; the caller and the called process must share it, so it falls back to APP_KEY.
        'secret' => env('MICROSERVICES_RPC_SECRET', env('APP_KEY', '')),
        'signature_ttl' => 30,

        // The cache store of the RpcService answers, shared by every process: the caller keeps an
        // answer, the owner forgets it when it writes. Null is the default store.
        'cache' => env('MICROSERVICES_RPC_CACHE_STORE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    | Named streams, like queue connections: each has a driver and that
    | driver's options. An event travels on the stream its stream() method
    | names, or on the default one. Drivers: `array` (in memory, for tests),
    | `null` (drops everything), and from their own packages `redis`
    | (mk-josias/redis-event-stream) and `queue` (mk-josias/queue-event-stream).
    | Any other driver resolves through a creator registered with extend().
    */

    'events' => [
        'stream' => env('MICROSERVICES_STREAM', 'default'),

        'streams' => [

            // Nothing is trimmed on write: stream:trim drops only what every consumer group acknowledged.
            'default' => [
                'driver' => env('MICROSERVICES_STREAM_DRIVER', 'array'), // redis or queue once its package is installed
                'outbox' => env('MICROSERVICES_STREAM_OUTBOX', false), // true: written with the business transaction, published by stream:publish
                'connection' => env('MICROSERVICES_STREAM_CONNECTION'), // null: the driver's own default connection
                'key' => 'microservices:events', // the Redis stream every service writes to, or the queue prefix
                'block' => 5_000,           // read block window, ms
                'count' => 50,              // entries per read
                'claim_after' => 60_000,    // reclaim entries a dead consumer left pending, ms
                'on_failure' => 'block',    // block: a failed entry is retried before later ones; skip: later ones go on
            ],

            // 'jobs' => [
            //     'driver' => 'queue', // mk-josias/queue-event-stream
            //     'connection' => env('MICROSERVICES_QUEUE_CONNECTION'),
            //     'key' => 'microservices-jobs', // each service reads {key}-{service}
            //     'sleep' => 1,                  // seconds to wait when the queue is empty
            // ],

        ],

        // 'event.name' => [Handler::class, …]
        'listen' => [],

        // The outbox and the consumption marks, in each service's own database.
        'tables' => [
            'publications' => 'event_publications',
            'consumptions' => 'event_consumptions',
        ],

        // Where stream:export and stream:import keep the published rows: `file` (JSON lines), or a store registered with ArchiveStores::extend().
        'archive' => env('MICROSERVICES_STREAM_ARCHIVE', 'file'),

        // Marks (event, handler) in the consumptions table inside the handler's transaction, so a
        // redelivery is a no-op. Null turns it on exactly when delivery is at-least-once.
        'guard' => env('MICROSERVICES_STREAM_GUARD'),

        // Keys of Laravel's Context carried in the envelope headers and restored around each handler.
        'propagate' => [],
    ],

];
