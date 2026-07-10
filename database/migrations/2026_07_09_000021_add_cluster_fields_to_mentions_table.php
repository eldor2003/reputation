<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mentions', function (Blueprint $table) {
            $table->foreignId('mention_cluster_id')
                ->nullable()
                ->after('person_id')
                ->constrained('mention_clusters')
                ->nullOnDelete();

            $table->string('simhash', 16)->nullable()->after('dedup_hash');
            $table->string('content_fingerprint', 64)->nullable()->after('simhash');

            $table->index('mention_cluster_id');
            $table->index('simhash');
            $table->index('content_fingerprint');
        });
    }

    public function down(): void
    {
        Schema::table('mentions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mention_cluster_id');
            $table->dropIndex(['simhash']);
            $table->dropIndex(['content_fingerprint']);
            $table->dropColumn(['simhash', 'content_fingerprint']);
        });
    }
};
