<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('type', 2)->default('PJ')->after('trade_name');
        });

        // Generaliza a coluna cnpj para document (agora aceita CPF de Pessoa Física também).
        DB::statement('ALTER TABLE empresas CHANGE cnpj document VARCHAR(18) NOT NULL');

        Schema::table('empresas', function (Blueprint $table) {
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });

        DB::statement('ALTER TABLE empresas CHANGE document cnpj VARCHAR(18) NOT NULL');
    }
};
