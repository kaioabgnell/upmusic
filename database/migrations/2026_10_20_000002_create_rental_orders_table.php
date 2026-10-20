<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pedido de Locação (specs/24 §4.2) — o "card" do Kanban próprio do módulo. Não se relaciona com
 * `cards`, `boards` nem `events`: departamento isolado do financeiro dos eventos.
 *
 * `subtotal` e `revenue_estimated` são desnormalizados (mantidos por
 * `RecalculateRentalOrderTotals`) para o Kanban e o rodapé não dependerem de SUM() por card.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_orders', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->nullable()->unique();
            $table->string('status', 20)->default('orcamento');
            $table->unsignedInteger('position')->default(0);

            // Cliente: Empresa cadastrada ou nome avulso em texto livre (specs/24 §2 Q8).
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->restrictOnDelete();
            $table->string('client_name', 150)->nullable();

            $table->string('location', 255)->nullable();
            $table->dateTime('start_at')->nullable();
            $table->dateTime('end_at')->nullable();

            $table->decimal('subtotal', 14, 2)->default(0);
            $table->string('discount_type', 10)->nullable();
            $table->decimal('discount_value', 12, 2)->default(0);
            $table->decimal('revenue_estimated', 14, 2)->default(0);
            $table->decimal('revenue_actual', 12, 2)->nullable();
            $table->date('revenue_due_date')->nullable();

            $table->boolean('has_availability_warning')->default(false);
            $table->boolean('has_return_divergence')->default(false);

            $table->text('notes')->nullable();

            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'position']);
            $table->index(['start_at', 'end_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_orders');
    }
};
