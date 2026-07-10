<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mention_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('delivery_digest_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('moderation_log_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel');
            $table->string('status');
            $table->json('card_payload');
            $table->text('message_text');
            $table->string('chat_id')->nullable();
            $table->string('telegram_message_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['mention_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_messages');
    }
};
