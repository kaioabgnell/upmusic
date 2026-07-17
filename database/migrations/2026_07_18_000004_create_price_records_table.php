<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fornecedor_categoria_id')->constrained('fornecedor_categorias')->cascadeOnDelete();
            $table->foreignId('fornecedor_id')->nullable()->constrained('fornecedores')->nullOnDelete();
            $table->foreignId('card_id')->nullable()->unique()->constrained('cards')->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->decimal('price', 15, 2);
            $table->date('reference_date');
            $table->string('notes', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Consulta da evolução histórica por categoria (ver specs/15).
            $table->index(['fornecedor_categoria_id', 'reference_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_records');
    }
};
