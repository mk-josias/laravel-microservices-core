<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Microservices\Config\Streamer;

/**
 * The consumption guard, in the CONSUMING service's own database — the mirror of the outbox.
 * The mark and the handler's own writes share one transaction, so a replay is a no-op and a
 * handler that fails leaves nothing behind. Two tables, because emitter and consumer never
 * share a database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(app(Streamer::class)->getConsumptionsTable(), function (Blueprint $table): void {
            $table->uuid('event_id');
            $table->string('handler');
            $table->timestamp('consumed_at');

            $table->primary(['event_id', 'handler']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(app(Streamer::class)->getConsumptionsTable());
    }
};
