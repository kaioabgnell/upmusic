<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Recebimentos parciais da receita do pedido de locação (specs/24 §4.4). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_order_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('received_at');
            $table->foreignId('finance_payment_source_id')->nullable()
                ->constrained('finance_payment_sources')->nullOnDelete();
            $table->string('notes', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('rental_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_receipts');
    }
};
