<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

it('deletes the consumption marks older than the given days and keeps the recent ones', function () {
    DB::table('event_consumptions')->insert([
        ['event_id' => (string) str()->uuid7(), 'handler' => 'Old', 'consumed_at' => now()->subDays(10)],
        ['event_id' => (string) str()->uuid7(), 'handler' => 'Recent', 'consumed_at' => now()->subDays(2)],
    ]);

    $this->artisan('stream:prune-consumptions --days=7')->expectsOutput('→ orders: 1 deleted')->assertSuccessful();

    expect(DB::table('event_consumptions')->pluck('handler')->all())->toBe(['Recent']);
});

it('refuses a retention under one day', function () {
    $this->artisan('stream:prune-consumptions --days=0')->assertFailed();
});
