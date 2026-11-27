<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quadros ligados em cada perfil (specs/28 §4). Substitui o vínculo por usuário (`user_board`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_profile_board', function (Blueprint $table) {
            $table->id();
            $table->foreignId('access_profile_id')->constrained('access_profiles')->cascadeOnDelete();
            $table->foreignId('board_id')->constrained('boards')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['access_profile_id', 'board_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_profile_board');
    }
};
