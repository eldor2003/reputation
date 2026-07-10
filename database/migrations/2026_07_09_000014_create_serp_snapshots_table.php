<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serp_snapshots', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('search_engine');
            $table->string('query');
            $table->timestamp('fetched_at');
            $table->decimal('response_time_ms', 10, 2);
            $table->string('serpapi_search_id');
            $table->string('screenshot_path')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['search_engine', 'query']);
            $table->index('fetched_at');
            $table->index('serpapi_search_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serp_snapshots');
    }
};
