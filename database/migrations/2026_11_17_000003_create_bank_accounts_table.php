<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contas bancárias (specs/26): cadastro global vinculado a uma ou mais empresas, e o vínculo
 * opcional em cada pagamento de custo (de onde sai) e em cada receita (para onde entra) do
 * Financeiro do Evento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('bank', 100);
            $table->string('name', 120);
            $table->string('agency', 20);
            $table->string('account_number', 30);
            $table->string('account_type', 20);              // BankAccountType
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            // Sem índice único: banco + agência + conta é único só entre os não excluídos
            // (validado no Form Request), para permitir recadastrar uma conta que foi para a lixeira.
            $table->index(['bank', 'agency', 'account_number'], 'bank_accounts_identity_idx');
        });

        Schema::create('bank_account_empresa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->unique(['bank_account_id', 'empresa_id']);
        });

        Schema::table('finance_payments', function (Blueprint $table) {
            $table->foreignId('bank_account_id')->nullable()->after('finance_payment_source_id')
                ->constrained('bank_accounts')->nullOnDelete();
        });

        Schema::table('finance_revenues', function (Blueprint $table) {
            $table->foreignId('bank_account_id')->nullable()->after('finance_payment_source_id')
                ->constrained('bank_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('finance_revenues', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_account_id');
        });

        Schema::table('finance_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_account_id');
        });

        Schema::dropIfExists('bank_account_empresa');
        Schema::dropIfExists('bank_accounts');
    }
};
