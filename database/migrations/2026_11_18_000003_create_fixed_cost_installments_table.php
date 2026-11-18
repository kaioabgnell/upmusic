<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Controle das parcelas já geradas (specs/27 §2.3). O índice único (custo, número) é a garantia de
 * que a mesma parcela nunca vira dois cards — nem com o comando agendado e o botão rodando juntos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_cost_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_cost_id')->constrained('fixed_costs')->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->date('due_date');
            $table->foreignId('card_id')->nullable()->constrained('cards')->nullOnDelete();
            $table->timestamp('generated_at');
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['fixed_cost_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_cost_installments');
    }
};
