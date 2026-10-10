<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Microservices\Config\Streamer;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(app(Streamer::class)->getPublicationsTable(), function (Blueprint $table): void {
            $table->unsignedSmallInteger('version')->default(1)->after('stream');
        });
    }

    public function down(): void
    {
        Schema::table(app(Streamer::class)->getPublicationsTable(), function (Blueprint $table): void {
            $table->dropColumn('version');
        });
    }
};
