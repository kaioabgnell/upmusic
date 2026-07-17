<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('setor_id')->nullable()->constrained('setores')->nullOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('color', 7)->default('#ff8c1e');
            $table->string('icon', 40)->nullable();
            $table->integer('position')->default(0);
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boards');
    }
};
