<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Pagamentos parciais de um custo do pedido de locação (specs/24 §4.6). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_cost_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_order_cost_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('paid_at');
            $table->foreignId('finance_payment_source_id')->nullable()
                ->constrained('finance_payment_sources')->nullOnDelete();
            $table->string('notes', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('rental_order_cost_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_cost_payments');
    }
};
