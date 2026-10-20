<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Custo simples do pedido de locação (specs/24 §4.5 / §2 Q21-Q27) — na prática, sublocação de itens
 * que faltam no inventário próprio. Vínculo com a linha de item é opcional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_order_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rental_order_item_id')->nullable()
                ->constrained('rental_order_items')->nullOnDelete();
            $table->string('description', 255);
            $table->decimal('estimated_value', 12, 2)->default(0);
            $table->decimal('actual_value', 12, 2)->nullable();
            $table->date('due_date')->nullable();
            $table->string('notes', 255)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('rental_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_order_costs');
    }
};
