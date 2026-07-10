<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routing_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routing_rule_id')->constrained()->cascadeOnDelete();
            $table->string('target_type');
            $table->json('target_config')->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['routing_rule_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routing_targets');
    }
};
