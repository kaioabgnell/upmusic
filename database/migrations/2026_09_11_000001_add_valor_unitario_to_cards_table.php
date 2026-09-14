<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Valor unitário e quantidade no card (specs/23 §2 e §6.2).
 *
 * O card só sabia falar em TOTAL; a linha de custo do Financeiro é `unitário × quantidade ×
 * diárias` (colunas geradas). Sem essa granularidade, todo card virava "1 × 1 × total": a coluna
 * "Vlr. unit." nunca teve um unitário de verdade, o Banco de Preços (specs/15) comparava totais de
 * contratações de tamanhos diferentes e o aviso de Preço Interno confrontava um total com um preço
 * por unidade. É o que a spec já registrava como limitação conhecida: "quantity / daily_count ← 1
 * (ajustável na grade; o card não tem essa granularidade)".
 *
 * `quantity` nasce com default 1 de propósito: card existente continua valendo exatamente o que
 * valia (unitário = total), então nada nos dados históricos muda de significado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->decimal('unit_value', 15, 2)->nullable()->after('actual_value');
            $table->decimal('quantity', 10, 2)->default(1)->after('unit_value');
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn(['unit_value', 'quantity']);
        });
    }
};
