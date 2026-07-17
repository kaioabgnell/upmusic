<?php

namespace App\Policies;

use App\Models\Board;
use App\Models\User;

class BoardPolicy
{
    // Admin liberado via Gate::before.

    public function viewAny(User $user): bool
    {
        return true; // todos veem a lista (filtrada por acesso no controller)
    }

    public function view(User $user, Board $board): bool
    {
        return $user->canAccessBoard($board);
    }

    public function create(User $user): bool
    {
        return $user->isCoordenador();
    }

    public function update(User $user, Board $board): bool
    {
        return $user->isCoordenador();
    }

    public function configure(User $user, Board $board): bool
    {
        return $user->isCoordenador();
    }

    public function delete(User $user, Board $board): bool
    {
        return $user->isCoordenador();
    }
}
