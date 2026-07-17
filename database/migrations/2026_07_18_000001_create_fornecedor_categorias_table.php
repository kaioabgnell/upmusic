<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fornecedor_categorias', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 120);
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        $now = now();
        DB::table('fornecedor_categorias')->insert(array_map(fn ($nome) => [
            'nome' => $nome,
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], [
            'Limpeza',
            'Segurança',
            'Som',
            'Cenografia',
            'Divulgação',
            'Estrutura Geral',
            'Estrutura Lounge',
            'Logística',
            'Projeto',
        ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('fornecedor_categorias');
    }
};
