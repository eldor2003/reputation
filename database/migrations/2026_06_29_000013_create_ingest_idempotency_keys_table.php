<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingest_idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key')->unique();
            $table->foreignId('mention_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['source_id', 'external_id']);
            $table->index('provider');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingest_idempotency_keys');
    }
};
