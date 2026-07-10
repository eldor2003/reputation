<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE ai_results
                ALTER COLUMN severity TYPE smallint
                USING CASE
                    WHEN severity ~ '^[0-9]+$' THEN severity::smallint
                    ELSE NULL
                END
            SQL);
        } else {
            Schema::table('ai_results', function (Blueprint $table) {
                $table->unsignedTinyInteger('severity')->nullable()->change();
            });
        }

        Schema::table('ai_results', function (Blueprint $table) {
            $table->string('person')->nullable()->after('category');
            $table->unsignedTinyInteger('confidence')->nullable()->after('person');
            $table->text('reasoning')->nullable()->after('confidence');
        });
    }

    public function down(): void
    {
        Schema::table('ai_results', function (Blueprint $table) {
            $table->dropColumn(['person', 'confidence', 'reasoning']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE ai_results ALTER COLUMN severity TYPE varchar(255) USING severity::varchar');
        } else {
            Schema::table('ai_results', function (Blueprint $table) {
                $table->string('severity')->nullable()->change();
            });
        }
    }
};
