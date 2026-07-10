<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mentions', function (Blueprint $table) {
            $table->string('author_id')->nullable()->after('author');
        });
    }

    public function down(): void
    {
        Schema::table('mentions', function (Blueprint $table) {
            $table->dropColumn('author_id');
        });
    }
};
