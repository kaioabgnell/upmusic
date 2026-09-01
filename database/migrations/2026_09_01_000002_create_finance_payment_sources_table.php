<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grupos de pagamento (specs/23 §4.2) — catálogo global que substitui as colunas fixas
 * "CAIXA EVENTO / SÓCIO 1 / SÓCIO 2 / TICKETEIRA / BAR" da planilha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_payment_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('kind', 20)->default('caixa');  // FinancePaymentSourceKind
            // Sócio opcionalmente vinculado a um usuário — habilita o acerto de sócios por pessoa.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('active')->default(true)->index();
            $table->integer('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_payment_sources');
    }
};
