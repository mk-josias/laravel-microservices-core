<?php

declare(strict_types=1);

use Microservices\Config\Services;

it('falls back to the application key for the RPC secret', function () {
    putenv('MICROSERVICES_RPC_SECRET');
    putenv('APP_KEY=app-key-value');

    try {
        expect((require dirname(__DIR__).'/config/microservices.php')['rpc']['secret'])->toBe('app-key-value');
    } finally {
        putenv('APP_KEY');
    }
});

it('declares this service first, then the ones it calls', function () {
    expect(app(Services::class)->getDeclared())->toBe(['orders', 'billing']);
});
