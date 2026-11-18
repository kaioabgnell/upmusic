<?php

namespace App\Policies;

use App\Models\User;

/** Centro de custo (specs/25): gestão igual a Setores — Admin (Gate::before) e Coordenador sem escopo de evento. */
class CostCenterPolicy
{
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
