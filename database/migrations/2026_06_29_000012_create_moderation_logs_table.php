<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mention_id')->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->string('moderator_id');
            $table->string('moderator_username')->nullable();
            $table->string('telegram_chat_id');
            $table->string('telegram_message_id')->nullable();
            $table->string('callback_query_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique('mention_id');
            $table->index(['action', 'created_at']);
            $table->index('moderator_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_logs');
    }
};
