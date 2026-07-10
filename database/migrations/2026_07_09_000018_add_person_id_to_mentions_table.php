<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mentions', function (Blueprint $table) {
            $table->foreignId('person_id')
                ->nullable()
                ->after('source_id')
                ->constrained('persons')
                ->nullOnDelete();

            $table->index('person_id');
        });
    }

    public function down(): void
    {
        Schema::table('mentions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('person_id');
        });
    }
};
