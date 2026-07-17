<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fornecedor_categorias', function (Blueprint $table) {
            $table->decimal('preco_interno', 15, 2)->nullable()->after('unidade');
        });
    }

    public function down(): void
    {
        Schema::table('fornecedor_categorias', function (Blueprint $table) {
            $table->dropColumn('preco_interno');
        });
    }
};
