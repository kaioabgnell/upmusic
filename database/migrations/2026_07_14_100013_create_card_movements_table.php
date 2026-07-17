<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained('cards')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('from_board_id')->nullable()->constrained('boards')->nullOnDelete();
            $table->foreignId('to_board_id')->nullable()->constrained('boards')->nullOnDelete();
            $table->foreignId('from_column_id')->nullable()->constrained('board_columns')->nullOnDelete();
            $table->foreignId('to_column_id')->nullable()->constrained('board_columns')->nullOnDelete();
            $table->string('type', 10); // column | board
            $table->timestamps();

            $table->index('card_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_movements');
    }
};
