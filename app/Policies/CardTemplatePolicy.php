<?php

namespace App\Policies;

use App\Domain\Enums\AccessModule;
use App\Models\User;

class CardTemplatePolicy
{
    // Admin liberado via Gate::before. Gestão de templates: módulo `templates` (specs/28). Usar um
    // template ao criar card depende só do quadro (TemplateController::import).
    public function viewAny(User $user): bool
    {
        return $user->hasModule(AccessModule::Templates);
    }

    public function create(User $user): bool
    {
        return $user->hasModule(AccessModule::Templates);
    }

    public function update(User $user): bool
    {
        return $user->hasModule(AccessModule::Templates);
    }

    public function delete(User $user): bool
    {
        return $user->hasModule(AccessModule::Templates);
    }
}
