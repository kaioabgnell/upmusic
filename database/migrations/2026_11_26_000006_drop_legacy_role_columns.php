<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove o modelo antigo de acesso (specs/28 §8.1): papel fixo, checkbox de Locações e o vínculo
 * usuário ↔ quadro. O down() recria tudo vazio; a migration anterior preenche no rollback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('user_board');

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn(['role', 'can_access_rentals']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('usuario')->after('password')->index();
            $table->boolean('can_access_rentals')->default(false)->after('role');
        });

        Schema::create('user_board', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('board_id')->constrained('boards')->cascadeOnDelete();
            $table->primary(['user_id', 'board_id']);
        });
    }
};
