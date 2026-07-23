<?php

namespace App\Policies;

use App\Models\User;

class SetorPolicy
{
    // Admin liberado via Gate::before. Gestão de cadastros: Coordenador.
    public function viewAny(User $user): bool
    {
        return $user->isCoordenador() && ! $user->isEventScoped();
    }

    public function create(User $user): bool
    {
        return $user->isCoordenador() && ! $user->isEventScoped();
    }

    public function update(User $user): bool
    {
        return $user->isCoordenador() && ! $user->isEventScoped();
    }

    public function delete(User $user): bool
    {
        return $user->isCoordenador() && ! $user->isEventScoped();
    }
}
