<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mentions', function (Blueprint $table) {
            $table->string('dedup_hash', 64)->nullable()->after('status');
            $table->boolean('is_duplicate')->default(false)->after('dedup_hash');
            $table->foreignId('original_mention_id')
                ->nullable()
                ->after('is_duplicate')
                ->constrained('mentions')
                ->nullOnDelete();

            $table->index('dedup_hash');
            $table->index('is_duplicate');
        });
    }

    public function down(): void
    {
        Schema::table('mentions', function (Blueprint $table) {
            $table->dropForeign(['original_mention_id']);
            $table->dropIndex(['dedup_hash']);
            $table->dropIndex(['is_duplicate']);
            $table->dropColumn(['dedup_hash', 'is_duplicate', 'original_mention_id']);
        });
    }
};
