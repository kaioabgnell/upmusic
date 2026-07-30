<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Valores negociados com/sem nota fiscal, independentes do "Banco de Preços" (estimated_value).
 * `negociado` é único e nullable (não duas colunas boolean) — as opções são mutuamente exclusivas
 * (radio no formulário), então uma coluna enum já impede o estado inválido "os dois marcados".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->decimal('valor_sem_nota', 15, 2)->nullable()->after('actual_value');
            $table->decimal('valor_com_nota', 15, 2)->nullable()->after('valor_sem_nota');
            $table->enum('negociado', ['sem_nota', 'com_nota'])->nullable()->after('valor_com_nota');
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn(['valor_sem_nota', 'valor_com_nota', 'negociado']);
        });
    }
};
