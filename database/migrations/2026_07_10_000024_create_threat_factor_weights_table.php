<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('threat_factor_weights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('factor_key', 50);
            $table->decimal('weight', 8, 4);
            $table->json('scoring_config');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['project_id', 'factor_key']);
            $table->index(['is_active', 'factor_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('threat_factor_weights');
    }
};
