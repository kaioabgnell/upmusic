<?php

namespace App\Policies;

use App\Domain\Enums\AccessModule;
use App\Models\User;

/** Centro de custo (specs/25): gestão igual a Setores — Admin (Gate::before) e Coordenador sem escopo de evento. */
class CostCenterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasModule(AccessModule::CostCenters);
    }

    public function create(User $user): bool
    {
        return $user->hasModule(AccessModule::CostCenters);
    }

    public function update(User $user): bool
    {
        return $user->hasModule(AccessModule::CostCenters);
    }

    public function delete(User $user): bool
    {
        return $user->hasModule(AccessModule::CostCenters);
    }
}
