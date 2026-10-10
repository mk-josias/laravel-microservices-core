<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Microservices\Config\Streamer;

/**
 * The outbox, in the EMITTING service's own database: the publication is written inside the
 * business transaction, so there is a single transactional write and no dual write left.
 */
return new class extends Migration
{
    public function up(): void
    {
        $name = app(Streamer::class)->getPublicationsTable();

        Schema::create($name, function (Blueprint $table) use ($name): void {
            // The row's place in the log: the only column that carries emission order.
            $table->bigIncrements('sequence');
            $table->uuid('id')->unique();
            $table->string('emitter');
            $table->string('name')->index();
            $table->json('payload');
            $table->json('headers');
            $table->timestamp('emitted_at');
            $table->json('recipients');
            $table->string('stream');
            $table->timestamp('published_at')->nullable();
            $table->string('stream_id')->nullable(); // the entry's id on a transport that tracks acknowledgements
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();

            // The only hot query: a service's pending rows, in emission order.
            $table->index(['emitter', 'published_at', 'sequence'], "{$name}_pending_index");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(app(Streamer::class)->getPublicationsTable());
    }
};
