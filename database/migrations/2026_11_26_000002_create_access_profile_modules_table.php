<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulos ligados em cada perfil (specs/28 §3). `module` guarda o valor do enum `AccessModule`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_profile_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('access_profile_id')->constrained('access_profiles')->cascadeOnDelete();
            $table->string('module', 40);
            $table->timestamps();

            $table->unique(['access_profile_id', 'module']);
            $table->index('module');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_profile_modules');
    }
};
