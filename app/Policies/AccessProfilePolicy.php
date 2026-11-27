<?php

namespace App\Policies;

use App\Models\AccessProfile;
use App\Models\User;

/** Perfis de acesso: só o Administrador (specs/28 §2), liberado pelo Gate::before. */
class AccessProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AccessProfile $profile): bool
    {
        return false;
    }

    public function delete(User $user, AccessProfile $profile): bool
    {
        return false;
    }
}
