<?php

namespace App\Policies;

use App\Domain\Enums\AccessModule;
use App\Models\User;

class FornecedorCategoriaPolicy
{
    // Admin liberado via Gate::before. Tela de cadastro: módulo `fornecedor_categorias` (specs/28).

    public function viewAny(User $user): bool
    {
        return $user->hasModule(AccessModule::FornecedorCategorias);
    }

    public function create(User $user): bool
    {
        return $user->hasModule(AccessModule::FornecedorCategorias);
    }

    /**
     * Cadastro rápido de categoria dentro do formulário de fornecedor: libera também com o módulo
     * Fornecedores, porque faz parte daquele fluxo (specs/28 §3).
     */
    public function quickCreate(User $user): bool
    {
        return $user->hasModule(AccessModule::FornecedorCategorias) || $user->hasModule(AccessModule::Fornecedores);
    }

    public function update(User $user): bool
    {
        return $user->hasModule(AccessModule::FornecedorCategorias);
    }

    public function delete(User $user): bool
    {
        return $user->hasModule(AccessModule::FornecedorCategorias);
    }
}
