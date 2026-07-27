<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ramos de atuação das empresas licitantes (ver specs/21 §6.2). As `keywords` alimentam o
 * desempate por afinidade de objeto do edital.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bid_business_lines', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->json('keywords')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bid_business_lines');
    }
};
