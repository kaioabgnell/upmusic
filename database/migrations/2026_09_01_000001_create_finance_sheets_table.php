<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Planilha financeira do evento (specs/23 §4.1) — substitui o arquivo
 * `FINANCEIRO - MODELO.xlsx`. Uma por evento, criada sob demanda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained('events')->cascadeOnDelete();
            // "Previsto 2" (refinamento pós-coleta de orçamentos). Desligado por padrão: eventos
            // simples usam só o Previsto 1, e a grade esconde o bloco inteiro quando falso.
            $table->boolean('uses_second_estimate')->default(false);
            $table->string('status', 20)->default('aberto');   // FinanceSheetStatus
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_sheets');
    }
};
