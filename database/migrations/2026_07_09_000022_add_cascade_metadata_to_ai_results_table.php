<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_results', function (Blueprint $table) {
            $table->string('cascade_tier', 20)->nullable()->after('model');
            $table->unsignedInteger('processing_time_ms')->nullable()->after('cascade_tier');
            $table->unsignedInteger('input_tokens')->nullable()->after('processing_time_ms');
            $table->unsignedInteger('output_tokens')->nullable()->after('input_tokens');
            $table->decimal('estimated_cost', 12, 8)->nullable()->after('output_tokens');
            $table->string('escalation_reason')->nullable()->after('estimated_cost');
        });
    }

    public function down(): void
    {
        Schema::table('ai_results', function (Blueprint $table) {
            $table->dropColumn([
                'cascade_tier',
                'processing_time_ms',
                'input_tokens',
                'output_tokens',
                'estimated_cost',
                'escalation_reason',
            ]);
        });
    }
};
