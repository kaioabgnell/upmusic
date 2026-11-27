<?php

namespace App\Policies;

use App\Domain\Enums\AccessModule;
use App\Models\CardCapture;
use App\Models\User;

class CardCapturePolicy
{
    // Admin liberado via Gate::before. Cada usuário só vê/mexe nas próprias capturas, e só enquanto
    // tiver o módulo Captura rápida no perfil de acesso (specs/28).

    public function view(User $user, CardCapture $capture): bool
    {
        return $capture->user_id === $user->id && $user->hasModule(AccessModule::Captures);
    }

    public function delete(User $user, CardCapture $capture): bool
    {
        return $this->view($user, $capture);
    }
}
