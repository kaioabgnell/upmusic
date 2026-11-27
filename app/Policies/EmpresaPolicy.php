<?php

namespace App\Policies;

use App\Domain\Enums\AccessModule;
use App\Models\User;

class EmpresaPolicy
{
    // Admin liberado via Gate::before.
    public function viewAny(User $user): bool
    {
        // Tela de cadastro: módulo `empresas` (specs/28). Buscar e cadastrar rapidamente uma empresa
        // dentro do card é livre (rotas empresas.search/quick).
        return $user->hasModule(AccessModule::Empresas);
    }

    public function create(User $user): bool
    {
        return $user->hasModule(AccessModule::Empresas);
    }

    public function update(User $user): bool
    {
        return $user->hasModule(AccessModule::Empresas);
    }

    public function delete(User $user): bool
    {
        return $user->hasModule(AccessModule::Empresas);
    }
}
