<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Centro de custo (specs/25): cadastro global que classifica linhas de CUSTOS e RECEITAS do
 * Financeiro do Evento.
 *
 * Código e nome são únicos só entre os NÃO excluídos (validação nos Form Requests) — por isso não
 * há índice único aqui: com soft delete, um índice único impediria recriar "MKT-01" depois de
 * excluir o antigo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->nullable();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('finance_cost_items', function (Blueprint $table) {
            $table->foreignId('cost_center_id')->nullable()->after('fornecedor_categoria_id')
                ->constrained('cost_centers')->nullOnDelete();
        });

        Schema::table('finance_revenues', function (Blueprint $table) {
            $table->foreignId('cost_center_id')->nullable()->after('description')
                ->constrained('cost_centers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('finance_revenues', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cost_center_id');
        });

        Schema::table('finance_cost_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cost_center_id');
        });

        Schema::dropIfExists('cost_centers');
    }
};
