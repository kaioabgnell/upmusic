<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Card de parcela de custo fixo aponta para a parcela (specs/27 §2.3) — é o que alimenta o selo. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->foreignId('fixed_cost_installment_id')->nullable()->after('event_id')
                ->constrained('fixed_cost_installments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fixed_cost_installment_id');
        });
    }
};
