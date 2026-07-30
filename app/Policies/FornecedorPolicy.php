<?php

namespace App\Policies;

use App\Models\User;

class FornecedorPolicy
{
    // Fornecedores é liberado a qualquer usuário autenticado (Admin/Coordenador/Usuário) — não é
    // um cadastro base restrito como Setores/Empresas/Eventos.
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user): bool
    {
        return true;
    }

    public function delete(User $user): bool
    {
        return true;
    }
}
