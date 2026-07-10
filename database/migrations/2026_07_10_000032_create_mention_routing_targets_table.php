<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mention_routing_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mention_route_id')->constrained()->cascadeOnDelete();
            $table->string('target_type');
            $table->json('target_config')->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index('mention_route_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mention_routing_targets');
    }
};
