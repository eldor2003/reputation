<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->string('alias');
            $table->string('normalized_alias');
            $table->string('type');
            $table->string('language');
            $table->foreignId('source_alias_id')->nullable()->constrained('person_aliases')->nullOnDelete();
            $table->boolean('is_auto_generated')->default(false);
            $table->timestamps();

            $table->unique(['person_id', 'normalized_alias', 'type']);
            $table->index(['person_id', 'type']);
            $table->index('normalized_alias');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_aliases');
    }
};
