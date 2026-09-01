<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pagamentos por linha de custo (specs/23 §4.4). PAGO = SUM(amount);
 * FALTA PAGAR = total_actual - PAGO. Tabela em vez das 5 colunas fixas da planilha: permite
 * pagamento parcial, data e auditoria, e os grupos mudam de evento para evento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finance_cost_item_id')->constrained('finance_cost_items')->cascadeOnDelete();
            $table->foreignId('finance_payment_source_id')->constrained('finance_payment_sources');
            $table->decimal('amount', 15, 2);
            $table->date('paid_at')->nullable();
            $table->string('notes', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['finance_cost_item_id', 'finance_payment_source_id'], 'finance_payments_item_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_payments');
    }
};
