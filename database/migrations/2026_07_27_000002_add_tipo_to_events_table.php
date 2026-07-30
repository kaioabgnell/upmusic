<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Tipo do evento (público/privado) — rádio obrigatório no formulário, "público" por padrão. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->enum('tipo', ['publico', 'privado'])->default('publico')->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
