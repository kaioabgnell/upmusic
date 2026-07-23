<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    // Admin liberado via Gate::before. Gestão de cadastros: Coordenador.
    public function viewAny(User $user): bool
    {
        // Coordenador restrito vê o menu Eventos, mas a lista é filtrada no controller (specs/20).
        return $user->isCoordenador();
    }

    // Coordenador restrito por evento (specs/20) não cria eventos (um evento novo, fora do seu
    // vínculo, sumiria da lista logo após criar).
    public function create(User $user): bool
    {
        return $user->isCoordenador() && ! $user->isEventScoped();
    }

    public function update(User $user, Event $event): bool
    {
        return $user->isCoordenador() && $this->withinScope($user, $event);
    }

    public function delete(User $user, Event $event): bool
    {
        return $user->isCoordenador() && $this->withinScope($user, $event);
    }

    /** Coordenador restrito só mexe nos eventos vinculados; coordenador comum, em qualquer um. */
    private function withinScope(User $user, Event $event): bool
    {
        $ids = $user->allowedEventIds();

        return $ids === null || $ids->contains($event->id);
    }
}
