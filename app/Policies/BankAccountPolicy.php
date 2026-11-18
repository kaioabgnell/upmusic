<?php

namespace App\Policies;

use App\Models\User;

/** Conta bancária (specs/26): gestão igual a Centros de custo — Admin (Gate::before) e Coordenador sem escopo de evento. */
class BankAccountPolicy
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
