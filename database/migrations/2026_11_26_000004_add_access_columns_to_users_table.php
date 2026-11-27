<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Administrador + Perfil de acesso no usuário (specs/28 §8.1). `restrictOnDelete`: o banco também
 * impede excluir um perfil que ainda tem usuários.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password')->index();
            $table->foreignId('access_profile_id')->nullable()->after('is_admin')
                ->constrained('access_profiles')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('access_profile_id');
            $table->dropIndex(['is_admin']);
            $table->dropColumn('is_admin');
        });
    }
};
