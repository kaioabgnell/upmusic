<?php

namespace App\Policies;

use App\Models\FixedCost;
use App\Models\User;

/**
 * Custos fixos (specs/27 §2.4): Admin (Gate::before) e Coordenador sem eventos vinculados — os
 * cards gerados não têm evento, então o coordenador restrito nem os veria.
 */
class FixedCostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageFixedCosts();
    }

    public function view(User $user, FixedCost $fixedCost): bool
    {
        return $user->canManageFixedCosts();
    }

    public function create(User $user): bool
    {
        return $user->canManageFixedCosts();
    }

    public function update(User $user, FixedCost $fixedCost): bool
    {
        return $user->canManageFixedCosts();
    }

    public function delete(User $user, FixedCost $fixedCost): bool
    {
        return $user->canManageFixedCosts();
    }
}
