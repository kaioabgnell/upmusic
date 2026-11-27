<?php

namespace App\Policies;

use App\Domain\Enums\AccessModule;
use App\Models\User;

/**
 * Autorização do módulo de Licitações (specs/21): módulo `bids` do perfil de acesso (specs/28).
 * Admin via Gate::before. O bloqueio real é este (e o middleware das rotas), não o menu escondido.
 *
 * Uma única policy atende todos os models `Bid*` porque a regra é idêntica em todos eles: o módulo
 * é ligado ou desligado inteiro.
 */
class BidModulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasModule(AccessModule::Bids);
    }

    public function view(User $user): bool
    {
        return $user->hasModule(AccessModule::Bids);
    }

    public function create(User $user): bool
    {
        return $user->hasModule(AccessModule::Bids);
    }

    public function update(User $user): bool
    {
        return $user->hasModule(AccessModule::Bids);
    }

    public function delete(User $user): bool
    {
        return $user->hasModule(AccessModule::Bids);
    }
}
