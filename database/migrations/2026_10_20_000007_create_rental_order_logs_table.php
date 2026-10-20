<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Histórico do pedido de locação (specs/24 §4.7) — etapas, avisos, motivos e justificativas. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_order_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['rental_order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_order_logs');
    }
};
