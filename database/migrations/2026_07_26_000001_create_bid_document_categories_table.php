<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categorias de documento do módulo de Licitações (ver specs/21 §6.3).
 * O `slug` é o contrato com a IA — os 6 nativos entram como enum no responseSchema do Gemini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bid_document_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 30)->unique();
            $table->string('name', 60);
            $table->string('color', 7)->default('#5a5a5c');
            $table->string('icon', 40)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('system')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bid_document_categories');
    }
};
