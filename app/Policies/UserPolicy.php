<?php

namespace App\Policies;

use App\Models\User;

/**
 * Usuários é só do Administrador (specs/28 §2), liberado pelo Gate::before. Nenhum perfil de acesso
 * libera esta tela: quem gerencia usuários pode dar a si mesmo qualquer acesso.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, User $target): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, User $target): bool
    {
        return false;
    }

    public function delete(User $user, User $target): bool
    {
        return false;
    }
}
