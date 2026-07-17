<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fornecedores', function (Blueprint $table) {
            $table->id();
            $table->string('type', 2); // PF | PJ
            $table->string('name', 180);
            $table->string('document', 18)->unique(); // CPF ou CNPJ
            $table->string('email', 150)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('category', 80)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fornecedores');
    }
};
