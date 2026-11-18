<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Cadastro de custos fixos recorrentes (specs/27 §2.3). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('board_id')->constrained('boards');
            $table->foreignId('board_column_id')->nullable()->constrained('board_columns')->nullOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->decimal('installment_value', 15, 2);
            $table->unsignedSmallInteger('installments_total');
            $table->unsignedSmallInteger('start_installment')->default(1);
            $table->date('first_due_date');
            $table->string('generation_mode', 20);
            $table->unsignedTinyInteger('lead_days')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['active', 'generation_mode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_costs');
    }
};
