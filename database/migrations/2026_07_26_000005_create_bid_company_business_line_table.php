<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Pivot empresa licitante ↔ ramo de atuação (ver specs/21 §6.2). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bid_company_business_line', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bid_company_id')->constrained('bid_companies')->cascadeOnDelete();
            $table->foreignId('bid_business_line_id')->constrained('bid_business_lines')->cascadeOnDelete();
            $table->timestamps();

            // Nome explícito: o gerado automaticamente passaria dos 64 caracteres do MySQL.
            $table->unique(['bid_company_id', 'bid_business_line_id'], 'bid_company_line_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bid_company_business_line');
    }
};
