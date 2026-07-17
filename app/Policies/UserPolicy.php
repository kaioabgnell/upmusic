<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Admin é liberado em tudo via Gate::before (AuthServiceProvider).
     * Aqui tratamos apenas o Coordenador; Usuário comum não gerencia usuários.
     */
    public function viewAny(User $user): bool
    {
        return $user->isCoordenador();
    }

    public function view(User $user, User $target): bool
    {
        return $user->isCoordenador();
    }

    public function create(User $user): bool
    {
        return $user->isCoordenador();
    }

    public function update(User $user, User $target): bool
    {
        // Coordenador só edita usuários comuns (não Admin nem outro Coordenador).
        return $user->isCoordenador() && $target->role === \App\Domain\Enums\UserRole::Usuario;
    }

    public function delete(User $user, User $target): bool
    {
        // Exclusão de usuários é exclusiva do Admin (coberta pelo Gate::before).
        return false;
    }
}
