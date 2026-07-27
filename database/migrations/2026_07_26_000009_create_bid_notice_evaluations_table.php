<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aptidão de cada empresa para um edital (ver specs/21 §6.8).
 * `verdict_at_analysis`/`score_at_analysis` são congelados na primeira avaliação e alimentam o
 * relatório histórico — recálculos posteriores não os sobrescrevem.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bid_notice_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bid_notice_id')->constrained('bid_notices')->cascadeOnDelete();
            $table->foreignId('bid_company_id')->constrained('bid_companies')->cascadeOnDelete();
            $table->enum('verdict', ['apta', 'apta_com_pendencias', 'inapta'])->default('inapta');
            $table->decimal('score', 5, 2)->default(0);
            $table->unsignedInteger('rank')->default(0);
            $table->unsignedInteger('met_count')->default(0);
            $table->unsignedInteger('expiring_count')->default(0);
            $table->unsignedInteger('missing_count')->default(0);
            $table->unsignedInteger('review_count')->default(0);
            $table->json('blockers')->nullable();
            $table->json('highlights')->nullable();
            $table->enum('verdict_at_analysis', ['apta', 'apta_com_pendencias', 'inapta'])->nullable();
            $table->decimal('score_at_analysis', 5, 2)->nullable();
            $table->dateTime('evaluated_at')->nullable();
            $table->timestamps();

            $table->unique(['bid_notice_id', 'bid_company_id'], 'bid_evaluation_notice_company_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bid_notice_evaluations');
    }
};
