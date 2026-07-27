<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conferência requisito × empresa (ver specs/21 §6.9). Linhas com `manual_override = true`
 * sobrevivem ao recálculo — o motor determinístico só reescreve as automáticas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bid_requirement_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bid_notice_requirement_id')->constrained('bid_notice_requirements')->cascadeOnDelete();
            $table->foreignId('bid_company_id')->constrained('bid_companies')->cascadeOnDelete();
            $table->foreignId('bid_document_id')->nullable()->constrained('bid_documents')->nullOnDelete();
            $table->enum('status', ['atendido', 'vencendo', 'vencido', 'ausente', 'conferir', 'nao_aplicavel'])->default('ausente');
            $table->enum('confidence', ['alta', 'media', 'baixa'])->default('alta');
            $table->string('reason', 255)->nullable();
            $table->boolean('manual_override')->default(false);
            $table->foreignId('overridden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['bid_notice_requirement_id', 'bid_company_id'], 'bid_match_requirement_company_unique');
            $table->index(['bid_company_id', 'status'], 'bid_match_company_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bid_requirement_matches');
    }
};
