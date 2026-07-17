<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_plan_id')->nullable()->constrained('financial_plans')->cascadeOnDelete();
            $table->foreignId('card_id')->nullable()->constrained('cards')->nullOnDelete();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->string('description', 180);
            $table->string('category', 80)->nullable();
            $table->decimal('estimated_value', 15, 2)->default(0);
            $table->decimal('actual_value', 15, 2)->default(0);
            $table->date('estimated_date')->nullable();
            $table->date('actual_date')->nullable();
            $table->timestamps();

            $table->index('financial_plan_id');
            $table->index('empresa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_entries');
    }
};
