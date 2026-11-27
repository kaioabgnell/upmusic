<?php

namespace App\Policies;

use App\Domain\Enums\AccessModule;
use App\Models\User;

class SetorPolicy
{
    // Admin liberado via Gate::before. Demais: módulo `setores` do perfil de acesso (specs/28).
    public function viewAny(User $user): bool
    {
        return $user->hasModule(AccessModule::Setores);
    }

    public function create(User $user): bool
    {
        return $user->hasModule(AccessModule::Setores);
    }

    public function update(User $user): bool
    {
        return $user->hasModule(AccessModule::Setores);
    }

    public function delete(User $user): bool
    {
        return $user->hasModule(AccessModule::Setores);
    }
}
