<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mention_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('model');
            $table->string('sentiment')->nullable();
            $table->string('severity')->nullable();
            $table->text('summary')->nullable();
            $table->json('raw_response');
            $table->timestamp('processed_at');
            $table->timestamps();

            $table->index(['mention_id', 'processed_at']);
            $table->index('provider');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_results');
    }
};
