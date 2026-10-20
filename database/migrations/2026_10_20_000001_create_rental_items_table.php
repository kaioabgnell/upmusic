<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventário da Gestão de Locações (specs/24 §4.1) — itens próprios (LED, mesa, telão, som...),
 * separados dos cadastros de eventos. Um registro por produto, sem tabela de aquisições (v1).
 *
 * `total_investment` é coluna GERADA (quantidade x valor do produto) — fica fora do $fillable, como
 * os totais do Financeiro do Evento (specs/23).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_items', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('sku', 50)->nullable()->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('total_investment', 14, 2)->storedAs('quantity * unit_cost');
            $table->date('purchase_date')->nullable();
            $table->decimal('rental_price', 12, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();

            // Exclusão lógica com motivo obrigatório (specs/24 §4.1 / Q11-Q22).
            $table->string('deletion_reason', 20)->nullable();
            $table->string('deletion_reason_other', 255)->nullable();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['active', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_items');
    }
};
