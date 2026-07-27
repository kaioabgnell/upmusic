<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Requisitos de habilitação extraídos do edital (ver specs/21 §6.7).
 * `source_excerpt` é obrigatório: sem o trecho de origem não há auditoria possível.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bid_notice_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bid_notice_id')->constrained('bid_notices')->cascadeOnDelete();
            $table->enum('kind', [
                'documento', 'cnae', 'porte', 'capital_social', 'patrimonio_liquido',
                'atestado_tecnico', 'registro_profissional', 'indice_contabil',
                'visita_tecnica', 'garantia_proposta', 'outro',
            ])->default('documento');
            $table->foreignId('bid_document_category_id')->nullable()->constrained('bid_document_categories')->nullOnDelete();
            $table->foreignId('bid_document_type_id')->nullable()->constrained('bid_document_types')->nullOnDelete();
            $table->string('name', 200);
            $table->string('description', 500)->nullable();
            $table->boolean('mandatory')->default(true);
            $table->json('expected')->nullable();
            $table->string('source_excerpt', 1000);
            $table->unsignedInteger('source_page')->nullable();
            $table->boolean('ignored')->default(false);
            $table->string('ignored_reason', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['bid_notice_id', 'sort_order'], 'bid_requirements_notice_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bid_notice_requirements');
    }
};
