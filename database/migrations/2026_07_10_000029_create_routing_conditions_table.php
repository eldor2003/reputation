<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routing_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routing_rule_id')->constrained()->cascadeOnDelete();
            $table->string('condition_type');
            $table->string('operator')->default('in');
            $table->json('value');
            $table->timestamps();

            $table->index('routing_rule_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routing_conditions');
    }
};
