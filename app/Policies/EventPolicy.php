<?php

namespace App\Policies;

use App\Domain\Enums\AccessModule;
use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    // Admin liberado via Gate::before. Demais: módulo `events` do perfil de acesso (specs/28).

    public function viewAny(User $user): bool
    {
        // Quem tem "Eventos visíveis: somente os selecionados" vê a lista filtrada no controller.
        return $user->hasModule(AccessModule::Events);
    }

    /**
     * Quem é restrito por evento também cria: o evento novo entra na hora nos eventos visíveis dele
     * (specs/28 §6, `CreateEvent`).
     */
    public function create(User $user): bool
    {
        return $user->hasModule(AccessModule::Events);
    }

    public function update(User $user, Event $event): bool
    {
        return $user->hasModule(AccessModule::Events) && $this->withinScope($user, $event);
    }

    public function delete(User $user, Event $event): bool
    {
        return $this->update($user, $event);
    }

    /** Restrito por evento só mexe nos eventos vinculados; sem restrição, em qualquer um. */
    private function withinScope(User $user, Event $event): bool
    {
        $ids = $user->allowedEventIds();

        return $ids === null || $ids->contains($event->id);
    }
}
