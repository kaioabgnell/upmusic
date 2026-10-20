<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Linha de item do pedido de locação (specs/24 §4.3). `item_name` e `unit_price` são snapshots:
 * mudar o cadastro do item depois não altera pedidos já lançados.
 *
 * `total` é coluna GERADA (quantidade x diárias x unitário) — fora do $fillable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rental_item_id')->constrained()->restrictOnDelete();
            $table->string('item_name', 150);
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('daily_count')->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total', 14, 2)->storedAs('quantity * daily_count * unit_price');

            // Conferência de devolução (specs/24 §6) — null até o pedido ser devolvido.
            $table->unsignedInteger('returned_quantity')->nullable();

            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index('rental_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_order_items');
    }
};
