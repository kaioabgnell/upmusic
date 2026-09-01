<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de descrições do modelo (specs/23 §4.8) — alimenta o autocomplete da coluna DESCRIÇÃO
 * com os 168 itens do arquivo `FINANCEIRO - MODELO.xlsx`, agrupados pela categoria escolhida.
 * Não é lista fechada: o usuário pode digitar qualquer descrição.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_item_presets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fornecedor_categoria_id')->constrained('fornecedor_categorias')->cascadeOnDelete();
            $table->string('description', 180);
            $table->boolean('active')->default(true)->index();
            $table->timestamps();

            $table->unique(['fornecedor_categoria_id', 'description'], 'finance_item_presets_cat_desc_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_item_presets');
    }
};
