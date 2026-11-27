<?php

namespace Tests\Concerns;

use App\Actions\AccessProfiles\MigrateRolesToAccessProfiles;
use App\Domain\Enums\AccessModule;
use App\Models\AccessProfile;
use App\Models\Board;
use App\Models\User;

/** Usuários de teste no modelo de Perfis de acesso (specs/28). */
trait CreatesAccessUsers
{
    /**
     * Usuário pelo pacote de acesso que a migração dá a cada papel antigo (specs/28 §9):
     * - admin       → Administrador;
     * - coordenador → perfil com o pacote do antigo Coordenador (Configurar quadros, cadastros,
     *                 Financeiro dos Eventos, Banco de Preços, Locações);
     * - usuario     → perfil com Fornecedores e Banco de Preços;
     * - sem-acesso  → perfil sem nenhum módulo.
     * `$modules` liga módulos a mais no mesmo perfil.
     *
     * @param  list<AccessModule>  $modules
     */
    protected function userAs(string $role, array $attributes = [], array $modules = []): User
    {
        $factory = User::factory();

        $factory = match ($role) {
            'admin' => $factory->admin(),
            'coordenador' => $factory->coordenador(),
            'usuario' => $factory->withModules(...MigrateRolesToAccessProfiles::USER_MODULES),
            'sem-acesso' => $factory->withModules(),
        };

        if ($modules) {
            $factory = $factory->withModules(...$modules);
        }

        return $factory->create($attributes + ['active' => true])->fresh();
    }

    /** Liga quadros no perfil de acesso do usuário (antes: vínculo `user_board`). */
    protected function giveBoards(User $user, Board|int ...$boards): User
    {
        $profile = $user->accessProfile ?? tap(
            AccessProfile::create(['name' => 'Perfil '.$user->id.'-'.uniqid()]),
            fn (AccessProfile $p) => $user->forceFill(['access_profile_id' => $p->id])->save(),
        );

        $profile->boards()->syncWithoutDetaching(array_map(fn ($b) => $b instanceof Board ? $b->id : $b, $boards));

        return $user->fresh();
    }
}
