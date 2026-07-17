<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained('boards')->cascadeOnDelete();
            $table->foreignId('board_column_id')->constrained('board_columns')->cascadeOnDelete();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->decimal('estimated_value', 15, 2)->nullable();
            $table->decimal('actual_value', 15, 2)->nullable();
            $table->date('due_date')->nullable();
            $table->string('priority', 10)->default('media');
            $table->string('origin', 20)->default('manual');
            $table->integer('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['board_id', 'board_column_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
