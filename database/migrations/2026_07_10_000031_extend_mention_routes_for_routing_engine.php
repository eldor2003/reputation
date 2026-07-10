<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mention_routes', function (Blueprint $table) {
            $table->foreignId('routing_rule_id')->nullable()->after('mention_id')->constrained()->nullOnDelete();
            $table->string('delivery_mode')->nullable()->after('channel');
            $table->boolean('skip_moderation')->default(false)->after('delivery_mode');
        });

        DB::table('mention_routes')
            ->where('priority', 'high')
            ->update(['priority' => 'immediate']);
    }

    public function down(): void
    {
        DB::table('mention_routes')
            ->where('priority', 'immediate')
            ->update(['priority' => 'high']);

        Schema::table('mention_routes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('routing_rule_id');
            $table->dropColumn(['delivery_mode', 'skip_moderation']);
        });
    }
};
