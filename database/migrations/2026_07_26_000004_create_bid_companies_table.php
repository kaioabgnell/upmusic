<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Empresas licitantes do grupo (ver specs/21 §6.1). Deliberadamente separada de `empresas`,
 * que é o cadastro de CLIENTES usado por cards, financeiro e preços.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bid_companies', function (Blueprint $table) {
            $table->id();
            $table->string('corporate_name', 180);
            $table->string('trade_name', 180)->nullable();
            $table->string('cnpj', 18);
            $table->enum('size', ['me', 'epp', 'demais'])->default('demais');
            $table->decimal('capital_social', 15, 2)->nullable();
            $table->decimal('net_worth', 15, 2)->nullable();
            $table->string('tax_regime', 40)->nullable();
            $table->json('cnaes')->nullable();
            $table->string('responsible_name', 180)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('zipcode', 9)->nullable();
            $table->string('address', 180)->nullable();
            $table->string('number', 20)->nullable();
            $table->string('complement', 120)->nullable();
            $table->string('district', 120)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state', 2)->nullable();
            $table->string('color', 7)->default('#0a0a0a');
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // CNPJ único entre os registros vivos — a unicidade real é garantida no Form Request
            // (Rule::unique()->whereNull('deleted_at')), já que MySQL não tem índice único parcial.
            $table->index('cnpj');
            $table->index('corporate_name');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bid_companies');
    }
};
