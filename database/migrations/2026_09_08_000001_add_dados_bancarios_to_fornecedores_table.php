<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dados bancários do fornecedor, para quem paga direto pelos dados cadastrados aqui (sem
 * depender de o fornecedor reenviar isso a cada pagamento). Todos opcionais: nem todo fornecedor
 * tem repasse bancário (ex.: pago via cartão/plataforma).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fornecedores', function (Blueprint $table) {
            $table->string('bank_name', 100)->nullable()->after('phone');
            $table->string('bank_agency', 20)->nullable()->after('bank_name');
            $table->string('bank_account', 30)->nullable()->after('bank_agency');
            // Formato livre de propósito: chave PIX pode ser CPF/CNPJ, e-mail, telefone ou
            // aleatória (UUID) — validar um formato fixo aqui rejeitaria chaves legítimas.
            $table->string('pix_key', 150)->nullable()->after('bank_account');
        });
    }

    public function down(): void
    {
        Schema::table('fornecedores', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'bank_agency', 'bank_account', 'pix_key']);
        });
    }
};
