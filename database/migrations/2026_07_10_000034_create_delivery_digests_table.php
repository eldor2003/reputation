<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_digests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('digest_type');
            $table->string('status');
            $table->unsignedInteger('item_count')->default(0);
            $table->text('message_text')->nullable();
            $table->string('chat_id')->nullable();
            $table->string('telegram_message_id')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'digest_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_digests');
    }
};
