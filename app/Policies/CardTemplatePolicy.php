<?php

namespace App\Policies;

use App\Models\User;

class CardTemplatePolicy
{
    // Admin liberado via Gate::before. Gestão de templates: Coordenador.
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
