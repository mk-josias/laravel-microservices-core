<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Microservices\Contracts\Stream\ArchiveStore;
use Microservices\Contracts\Stream\Bus;
use Microservices\Exceptions\ConfigurationException;
use Microservices\Services\Stream\Outbox\ArchiveStores;
use Microservices\Tests\Fixtures\App\Events\OrderPlaced;

/** Three published orders of this service, emitted on the 1st, 2nd and 3rd of September. */
function publishedOrders(): void
{
    config()->set('microservices.events.streams.default.outbox', true);

    foreach ([1, 2, 3] as $id) {
        app(Bus::class)->emit(new OrderPlaced($id));
        DB::table('event_publications')->where('payload->id', $id)->update(['emitted_at' => "2026-09-0{$id} 10:00:00"]);
    }

    test()->artisan('stream:publish', ['--once' => true])->assertSuccessful();
}

/** @return list<int> */
function remainingOrders(): array
{
    return DB::table('event_publications')->orderBy('sequence')->pluck('payload')
        ->map(fn (string $payload): int => (int) json_decode($payload, true)['id'])->all();
}

it('exports only the rows emitted in the window --since and --until name', function () {
    publishedOrders();
    $path = tempnam(sys_get_temp_dir(), 'archive');

    $this->artisan("stream:export {$path} --since=2026-09-02 --until=2026-09-03")->assertSuccessful();

    expect(remainingOrders())->toBe([1, 3])
        ->and(count(file($path)))->toBe(1);
});

it('imports only the rows matching its filters, back as pending publications', function () {
    publishedOrders();
    $path = tempnam(sys_get_temp_dir(), 'archive');
    $this->artisan("stream:export {$path}")->assertSuccessful();

    $this->artisan("stream:import {$path} --service=orders --where=payload.id=2")->assertSuccessful();

    expect(remainingOrders())->toBe([2])
        ->and(DB::table('event_publications')->whereNull('published_at')->count())->toBe(1);
});

it('keeps the rows in the store --store names, registered with extend()', function () {
    publishedOrders();
    $memory = new class implements ArchiveStore
    {
        /** @var array<string, list<array<string, mixed>>> */
        public array $rows = [];

        public function write(string $target, array $rows): void
        {
            $this->rows[$target] = [...$this->rows[$target] ?? [], ...$rows];
        }

        public function read(string $source): iterable
        {
            return $this->rows[$source] ?? [];
        }
    };
    app(ArchiveStores::class)->extend('memory', fn () => $memory);

    $this->artisan('stream:export september --store=memory')->assertSuccessful();
    $this->artisan('stream:import september --store=memory --since=2026-09-03')->assertSuccessful();

    expect(count($memory->rows['september']))->toBe(3)
        ->and(remainingOrders())->toBe([3]);
});

it('fails on a store nobody registered', function () {
    $this->artisan('stream:export somewhere --store=s3');
})->throws(ConfigurationException::class, 'Archive store [s3] is not supported');
