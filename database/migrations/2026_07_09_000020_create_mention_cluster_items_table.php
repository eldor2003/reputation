<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mention_cluster_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mention_cluster_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mention_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_canonical')->default(false);
            $table->decimal('similarity_score', 5, 4)->nullable();
            $table->timestamp('joined_at');
            $table->timestamps();

            $table->index(['mention_cluster_id', 'is_canonical']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mention_cluster_items');
    }
};
