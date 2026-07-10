<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_digest_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_digest_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('delivery_message_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('mention_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('digest_type');
            $table->string('status');
            $table->json('card_payload');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamp('queued_at');
            $table->timestamps();

            $table->index(['project_id', 'digest_type', 'status']);
            $table->index(['mention_id', 'digest_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_digest_items');
    }
};
