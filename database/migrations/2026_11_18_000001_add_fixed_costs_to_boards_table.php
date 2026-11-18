<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tipo de quadro "custos fixos" (specs/27 §1.1). Liga a chave no quadro "Custos Fixos" que já existe
 * — procurado pelo nome, nunca pelo id, e só se ele não alimentar o Financeiro (as duas chaves são
 * exclusivas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            $table->boolean('fixed_costs')->default(false)->after('feeds_finance');
        });

        DB::table('boards')
            ->where('name', 'Custos Fixos')
            ->where('feeds_finance', false)
            ->update(['fixed_costs' => true]);
    }

    public function down(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            $table->dropColumn('fixed_costs');
        });
    }
};
