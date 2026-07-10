<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serp_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('serp_snapshot_id')->constrained('serp_snapshots')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('title');
            $table->string('url', 2048);
            $table->text('snippet')->nullable();
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->unique(['serp_snapshot_id', 'position']);
            $table->index('fetched_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serp_results');
    }
};
