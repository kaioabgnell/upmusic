<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aba CUSTOS da planilha (specs/23 §4.3).
 *
 * Os três `total_*` são colunas GERADAS (STORED) que reproduzem as fórmulas J/L/N do arquivo
 * (`TOTAL = VALOR UNIT. x QUANT. x DIÁRIAS`). Isso torna impossível existir linha com total fora
 * de sincronia e deixa os SUM() do resumo lendo coluna indexável. Elas NÃO entram em $fillable —
 * o banco recusa INSERT/UPDATE que as mencione (ver App\Models\FinanceCostItem).
 *
 * `unit_actual` e `unit_estimated_2` são nullable de propósito: "0,00" (saiu de graça) e "ainda não
 * aconteceu" são estados diferentes, e o resumo precisa distinguir os dois para não exibir economia
 * inexistente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_cost_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finance_sheet_id')->constrained('finance_sheets')->cascadeOnDelete();

            // ITEM (coluna A) — reaproveita a categoria de fornecedor que já existe (specs/15).
            $table->foreignId('fornecedor_categoria_id')->nullable()
                ->constrained('fornecedor_categorias')->nullOnDelete();
            $table->string('description', 180);                       // DESCRIÇÃO (B)

            $table->string('status', 30)->default('orcamento');       // FinanceCostStatus (C)
            $table->boolean('status_auto')->default(true);            // ainda derivado dos documentos?
            $table->string('art_status', 20)->default('nao_tem');     // FinanceArtStatus (D)

            // EMPRESA (E): fornecedor cadastrado ou, sem cadastro, texto livre.
            $table->foreignId('fornecedor_id')->nullable()->constrained('fornecedores')->nullOnDelete();
            $table->string('supplier_name', 180)->nullable();

            // AUTORIZADO POR (F): usuário do sistema ou texto livre (autorização externa).
            $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('authorized_by_name', 120)->nullable();

            // Compartilhados pelos três cenários — é o que a planilha faz (G e H entram nas 3 fórmulas).
            $table->decimal('daily_count', 8, 2)->default(1);         // DIÁRIAS (G)
            $table->decimal('quantity', 10, 2)->default(1);           // QUANT.  (H)

            $table->decimal('unit_estimated_1', 15, 2)->default(0);   // VALOR UNIT. previsto 1 (I)
            $table->decimal('unit_estimated_2', 15, 2)->nullable();   // VALOR UNIT. previsto 2 (K)
            $table->decimal('unit_actual', 15, 2)->nullable();        // VALOR UNIT. realizado  (M)

            $table->decimal('total_estimated_1', 15, 2)
                ->storedAs('unit_estimated_1 * quantity * daily_count');
            $table->decimal('total_estimated_2', 15, 2)
                ->storedAs('COALESCE(unit_estimated_2, 0) * quantity * daily_count');
            $table->decimal('total_actual', 15, 2)
                ->storedAs('COALESCE(unit_actual, 0) * quantity * daily_count');

            // Origem no Kanban. Nullable: linha nascida direto no financeiro (taxa, guia) não tem card.
            $table->foreignId('card_id')->nullable()->constrained('cards')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->integer('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            // Nomes explícitos: os automáticos do Laravel estouram o limite de 64 caracteres do MySQL.
            $table->index(['finance_sheet_id', 'position'], 'finance_cost_items_sheet_position_idx');
            $table->index(['finance_sheet_id', 'fornecedor_categoria_id'], 'finance_cost_items_sheet_categoria_idx');
            $table->index(['finance_sheet_id', 'status'], 'finance_cost_items_sheet_status_idx');
            $table->index('card_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_cost_items');
    }
};
