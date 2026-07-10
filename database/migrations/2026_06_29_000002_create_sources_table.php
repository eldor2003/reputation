<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('external_id');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->json('config')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'type', 'external_id']);
            $table->index(['project_id', 'is_active']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};
