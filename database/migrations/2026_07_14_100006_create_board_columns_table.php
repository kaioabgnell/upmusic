<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('board_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained('boards')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('color', 7)->nullable();
            $table->integer('position')->default(0);
            $table->boolean('is_final')->default(false);  // habilita envio p/ outro quadro
            $table->boolean('is_entry')->default(false);  // recebe cards do formulário externo
            $table->timestamps();

            $table->index(['board_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_columns');
    }
};
