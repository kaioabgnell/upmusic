<?php

namespace App\Policies;

use App\Models\User;

class EmpresaPolicy
{
    // Admin liberado via Gate::before.
    public function viewAny(User $user): bool
    {
        // Usuário também pode visualizar/selecionar empresas (ver specs/05).
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isCoordenador();
    }

    public function update(User $user): bool
    {
        return $user->isCoordenador();
    }

    public function delete(User $user): bool
    {
        return $user->isCoordenador();
    }
}
