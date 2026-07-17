<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aposenta o módulo de Serviços (ver specs/15). O histórico de preços passou a ser ancorado na
 * Categoria de Fornecedor (`price_records`). Como não há mapeamento confiável Serviço→Categoria,
 * os dados de `service_prices` não são migrados — o `down()` recria as tabelas vazias por segurança.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('service_prices');
        Schema::dropIfExists('services');
    }

    public function down(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('category', 80)->nullable();
            $table->string('unit', 30)->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('service_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->foreignId('fornecedor_id')->nullable()->constrained('fornecedores')->nullOnDelete();
            $table->foreignId('card_id')->nullable()->constrained('cards')->nullOnDelete();
            $table->decimal('price', 15, 2);
            $table->date('reference_date');
            $table->string('notes', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['service_id', 'empresa_id', 'reference_date']);
        });
    }
};
