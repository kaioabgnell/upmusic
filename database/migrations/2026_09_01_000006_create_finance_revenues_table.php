<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Aba RECEITAS da planilha (specs/23 §4.6). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_revenues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finance_sheet_id')->constrained('finance_sheets')->cascadeOnDelete();
            $table->string('category', 40);                        // FinanceRevenueCategory (A)
            // "vai com a descrição do que entrou de patrocínio" — patrocinador/cota/lote.
            $table->string('description', 180)->nullable();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->decimal('estimated_value', 15, 2)->default(0);  // VALOR PREVISTO (B)
            $table->decimal('actual_value', 15, 2)->default(0);     // VALOR REALIZADO (C)
            $table->decimal('received_value', 15, 2)->default(0);   // RECEBIDO (D)
            $table->decimal('pending_value', 15, 2)
                ->storedAs('actual_value - received_value');        // FALTA RECEBER (E)
            $table->foreignId('finance_payment_source_id')->nullable()
                ->constrained('finance_payment_sources');           // RECEBIDO POR (F)
            $table->date('received_at')->nullable();
            $table->string('notes', 255)->nullable();               // OBS (G)
            $table->integer('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['finance_sheet_id', 'position'], 'finance_revenues_sheet_position_idx');
            $table->index(['finance_sheet_id', 'category'], 'finance_revenues_sheet_category_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_revenues');
    }
};
