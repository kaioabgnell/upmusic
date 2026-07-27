<?php

namespace App\Policies;

use App\Models\User;

/**
 * Autorização do módulo de Licitações (ver specs/21 §7): acesso exclusivo do Admin.
 *
 * O `Gate::before` do AuthServiceProvider já libera o Admin; estas policies existem para negar
 * explicitamente Coordenador e Usuário — inclusive se o `before` mudar um dia. O bloqueio real é
 * este, e não o menu escondido na sidebar.
 *
 * Uma única policy atende todos os models `Bid*` porque a regra é idêntica em todos eles: o módulo
 * é do Admin, inteiro. Se algum dia um perfil ganhar acesso parcial, separar por model.
 */
class BidModulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user): bool
    {
        return $user->isAdmin();
    }
}
