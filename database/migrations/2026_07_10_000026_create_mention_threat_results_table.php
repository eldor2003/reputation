<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mention_threat_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mention_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_result_id')->nullable()->constrained('ai_results')->nullOnDelete();
            $table->string('threat_level', 2);
            $table->decimal('threat_score', 8, 2);
            $table->json('factor_scores');
            $table->timestamp('assessed_at');
            $table->timestamps();

            $table->index(['mention_id', 'assessed_at']);
            $table->index('threat_level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mention_threat_results');
    }
};
