<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_template_id')->constrained('card_templates')->cascadeOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->foreignId('default_column_id')->nullable()->constrained('board_columns')->nullOnDelete();
            $table->json('default_fields')->nullable();
            $table->integer('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_template_items');
    }
};
