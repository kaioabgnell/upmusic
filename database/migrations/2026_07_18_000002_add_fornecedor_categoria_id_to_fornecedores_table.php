<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fornecedores', function (Blueprint $table) {
            $table->foreignId('fornecedor_categoria_id')->nullable()->after('document')
                ->constrained('fornecedor_categorias')->nullOnDelete();
        });

        // Casa o texto livre antigo (`category`) com as categorias novas por nome, antes de descartar a coluna.
        $categoriaIds = DB::table('fornecedor_categorias')->pluck('id', 'nome');
        foreach (DB::table('fornecedores')->whereNotNull('category')->get(['id', 'category']) as $fornecedor) {
            foreach ($categoriaIds as $nome => $id) {
                if (mb_strtolower($nome) === mb_strtolower(trim($fornecedor->category))) {
                    DB::table('fornecedores')->where('id', $fornecedor->id)->update(['fornecedor_categoria_id' => $id]);
                    break;
                }
            }
        }

        Schema::table('fornecedores', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }

    public function down(): void
    {
        Schema::table('fornecedores', function (Blueprint $table) {
            $table->string('category', 80)->nullable()->after('document');
        });

        Schema::table('fornecedores', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fornecedor_categoria_id');
        });
    }
};
