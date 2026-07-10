<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mention_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->string('message_id')->nullable();
            $table->string('chat_id');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['mention_id', 'status']);
            $table->index('chat_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_notifications');
    }
};
