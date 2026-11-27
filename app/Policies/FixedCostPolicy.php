<?php

namespace App\Policies;

use App\Domain\Enums\AccessModule;
use App\Models\FixedCost;
use App\Models\User;

/**
 * Custos fixos (specs/27): módulo `fixed_costs` do perfil de acesso (specs/28), que também abre o
 * quadro de custos fixos. Admin via Gate::before.
 */
class FixedCostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasModule(AccessModule::FixedCosts);
    }

    public function view(User $user, FixedCost $fixedCost): bool
    {
        return $user->hasModule(AccessModule::FixedCosts);
    }

    public function create(User $user): bool
    {
        return $user->hasModule(AccessModule::FixedCosts);
    }

    public function update(User $user, FixedCost $fixedCost): bool
    {
        return $user->hasModule(AccessModule::FixedCosts);
    }

    public function delete(User $user, FixedCost $fixedCost): bool
    {
        return $user->hasModule(AccessModule::FixedCosts);
    }
}
