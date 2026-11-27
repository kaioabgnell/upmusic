<?php

namespace App\Policies;

use App\Domain\Enums\AccessModule;
use App\Models\User;

class FornecedorPolicy
{
    // Tela de cadastro de Fornecedores: módulo `fornecedores` (specs/28). Escolher e cadastrar
    // rapidamente um fornecedor dentro do card continua livre para quem acessa o quadro.
    public function viewAny(User $user): bool
    {
        return $user->hasModule(AccessModule::Fornecedores);
    }

    public function create(User $user): bool
    {
        return $user->hasModule(AccessModule::Fornecedores);
    }

    public function update(User $user): bool
    {
        return $user->hasModule(AccessModule::Fornecedores);
    }

    public function delete(User $user): bool
    {
        return $user->hasModule(AccessModule::Fornecedores);
    }
}
