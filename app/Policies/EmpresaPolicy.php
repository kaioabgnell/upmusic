<?php

namespace App\Policies;

use App\Models\User;

class EmpresaPolicy
{
    // Admin liberado via Gate::before.
    public function viewAny(User $user): bool
    {
        // Usuário também pode visualizar/selecionar empresas (ver specs/05). Coordenador restrito por
        // evento (specs/20) não acessa o cadastro de Empresas.
        return ! $user->isEventScoped();
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
