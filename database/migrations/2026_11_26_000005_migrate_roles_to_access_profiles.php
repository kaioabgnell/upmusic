<?php

use App\Actions\AccessProfiles\MigrateRolesToAccessProfiles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migração de dados dos papéis antigos para Administrador + Perfil de acesso (specs/28 §9). A regra
 * mora na Action, testável isoladamente.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(MigrateRolesToAccessProfiles::class)->execute();
    }

    /**
     * Roda depois do down() da migration seguinte, que recria `role`, `can_access_rentals` e
     * `user_board` vazios. Volta o papel por aproximação: Administrador → admin; perfil com
     * "Configurar quadros" (o poder que só o coordenador tinha) → coordenador; o resto → usuario.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('users', 'role')) {
            return;
        }

        $withModule = fn (string $module) => DB::table('access_profile_modules')
            ->where('module', $module)->select('access_profile_id');

        DB::table('users')->update(['role' => 'usuario']);
        DB::table('users')->whereIn('access_profile_id', $withModule('boards_configure'))->update(['role' => 'coordenador']);
        DB::table('users')->where('is_admin', true)->update(['role' => 'admin']);

        DB::table('users')->where('role', 'usuario')
            ->whereIn('access_profile_id', $withModule('rentals'))
            ->update(['can_access_rentals' => true]);

        $links = DB::table('users')
            ->join('access_profile_board', 'access_profile_board.access_profile_id', '=', 'users.access_profile_id')
            ->where('users.role', 'usuario')
            ->get(['users.id as user_id', 'access_profile_board.board_id'])
            ->map(fn ($row) => ['user_id' => $row->user_id, 'board_id' => $row->board_id])
            ->all();

        if ($links) {
            DB::table('user_board')->insertOrIgnore($links);
        }
    }
};
