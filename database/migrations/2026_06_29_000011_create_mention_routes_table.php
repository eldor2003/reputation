<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mention_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mention_id')->constrained()->cascadeOnDelete();
            $table->boolean('should_notify');
            $table->string('priority');
            $table->string('channel');
            $table->text('reason');
            $table->timestamp('created_at')->useCurrent();

            $table->unique('mention_id');
            $table->index(['should_notify', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mention_routes');
    }
};
