<?php

namespace App\Policies;

use App\Models\User;

class FornecedorPolicy
{
    // Admin liberado via Gate::before.
    public function viewAny(User $user): bool
    {
        return $user->isCoordenador();
    }

    public function create(User $user): bool
    {
        return $user->isCoordenador();
    }

    public function update(User $user): bool
    {
        return $user->isCoordenador();
    }

    public function delete(User $user): bool
    {
        return $user->isCoordenador();
    }
}
