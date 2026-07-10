<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_results', function (Blueprint $table) {
            $table->string('validation_status', 20)->nullable()->after('escalation_reason');
            $table->unsignedTinyInteger('validation_retry_count')->default(0)->after('validation_status');
            $table->boolean('injection_detected')->default(false)->after('validation_retry_count');
            $table->string('guard_reason')->nullable()->after('injection_detected');
        });
    }

    public function down(): void
    {
        Schema::table('ai_results', function (Blueprint $table) {
            $table->dropColumn([
                'validation_status',
                'validation_retry_count',
                'injection_detected',
                'guard_reason',
            ]);
        });
    }
};
