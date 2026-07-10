<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('person_id')->nullable()->constrained('persons')->nullOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('rule_priority')->default(100);
            $table->string('routing_priority');
            $table->string('delivery_mode');
            $table->boolean('auto_skip')->default(false);
            $table->boolean('skip_moderation')->default(false);
            $table->text('reason_template')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_fallback')->default(false);
            $table->timestamps();

            $table->index(['is_active', 'rule_priority']);
            $table->index(['project_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routing_rules');
    }
};
