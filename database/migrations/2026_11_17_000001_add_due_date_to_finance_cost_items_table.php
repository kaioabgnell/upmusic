<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_cost_items', function (Blueprint $table) {
            // Data de vencimento do pagamento da despesa. Informada no modal "Enviar/Sincronizar
            // com o Financeiro" do card ou direto na grade de CUSTOS. Null = sem vencimento.
            $table->date('due_date')->nullable()->after('unit_actual');
        });
    }

    public function down(): void
    {
        Schema::table('finance_cost_items', function (Blueprint $table) {
            $table->dropColumn('due_date');
        });
    }
};
