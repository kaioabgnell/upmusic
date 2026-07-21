<?php

namespace App\Policies;

use App\Models\CardCapture;
use App\Models\User;

class CardCapturePolicy
{
    // Admin liberado via Gate::before. Autorização por dono: cada usuário só vê/mexe nas próprias capturas.

    public function view(User $user, CardCapture $capture): bool
    {
        return $capture->user_id === $user->id;
    }

    public function delete(User $user, CardCapture $capture): bool
    {
        return $capture->user_id === $user->id;
    }
}
