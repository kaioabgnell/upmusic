<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quadro que alimenta o Financeiro (specs/23 §4.9). Card que entra num quadro com esta flag
 * sincroniza sozinho com a planilha do evento — mesmo padrão de `boards.allows_supplier_form`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            $table->boolean('feeds_finance')->default(false)->after('active');
        });

        // O quadro "Financeiro" do seeder é exatamente o ponto do fluxo em que a planilha começava
        // a ser preenchida à mão; já nasce ligado.
        DB::table('boards')->where('name', 'Financeiro')->update(['feeds_finance' => true]);
    }

    public function down(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            $table->dropColumn('feeds_finance');
        });
    }
};
