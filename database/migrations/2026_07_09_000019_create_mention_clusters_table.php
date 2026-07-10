<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mention_clusters', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('canonical_mention_id')->nullable()->constrained('mentions')->nullOnDelete();
            $table->string('simhash', 16)->nullable();
            $table->string('content_fingerprint', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'simhash']);
            $table->index('content_fingerprint');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mention_clusters');
    }
};
