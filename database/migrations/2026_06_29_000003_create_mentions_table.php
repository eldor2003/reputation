<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mentions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_id')->constrained()->restrictOnDelete();
            $table->string('external_id');
            $table->string('language', 10)->nullable();
            $table->string('author')->nullable();
            $table->string('title')->nullable();
            $table->longText('content');
            $table->string('url', 2048)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('received_at');
            $table->json('metadata')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->unique(['source_id', 'external_id']);
            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'published_at']);
            $table->index('received_at');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentions');
    }
};
