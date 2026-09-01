<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** ACERTO SÓCIOS do RESUMO GERAL (specs/23 §4.7). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_partner_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finance_sheet_id')->constrained('finance_sheets')->cascadeOnDelete();
            $table->foreignId('finance_payment_source_id')->nullable()
                ->constrained('finance_payment_sources');
            $table->string('partner_name', 120);
            $table->decimal('percentage', 5, 2)->default(0);   // PORCENTAGEM
            $table->decimal('amount', 15, 2)->default(0);      // TOTAL
            // amount = resultado realizado x percentage, exceto quando digitado à mão.
            $table->boolean('manual_amount')->default(false);
            $table->timestamps();

            $table->index('finance_sheet_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_partner_settlements');
    }
};
