<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mention_raws', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mention_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->json('payload');
            $table->timestamps();

            $table->index('provider');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mention_raws');
    }
};
