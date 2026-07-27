<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo canônico de tipos de documento (ver specs/21 §6.4) — peça central da precisão do
 * matching: o `slug` vira enum no schema do Gemini e os `aliases` resolvem variações de nome.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bid_document_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bid_document_category_id')->constrained('bid_document_categories')->restrictOnDelete();
            $table->string('slug', 60)->unique();
            $table->string('name', 150);
            $table->json('aliases')->nullable();
            $table->string('issuer', 120)->nullable();
            $table->unsignedInteger('default_validity_days')->nullable();
            $table->boolean('requires_control_code')->default(false);
            // Tipos essenciais compõem o denominador da "saúde documental" da empresa (specs/21 §9.1).
            $table->boolean('essential')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bid_document_types');
    }
};
