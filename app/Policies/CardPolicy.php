<?php

namespace App\Policies;

use App\Models\Board;
use App\Models\Card;
use App\Models\User;

class CardPolicy
{
    // Admin liberado via Gate::before.

    /**
     * O responsável atual sempre lê o próprio card, mesmo sem acesso ao quadro (specs/22). O escopo
     * por evento (specs/20) continua valendo como limite absoluto: ser responsável não fura a
     * restrição de eventos de um coordenador restrito.
     */
    public function view(User $user, Card $card): bool
    {
        $canRead = $user->canAccessBoard($card->board) || $card->assignee_id === $user->id;

        return $canRead && $this->withinEventScope($user, $card);
    }

    public function create(User $user, Board $board): bool
    {
        return $user->canAccessBoard($board);
    }

    public function update(User $user, Card $card): bool
    {
        return $user->canAccessBoard($card->board) && $this->withinEventScope($user, $card);
    }

    public function delete(User $user, Card $card): bool
    {
        return $user->canAccessBoard($card->board) && $this->withinEventScope($user, $card);
    }

    /**
     * Coordenador restrito por evento (specs/20) só enxerga/opera cards dos seus eventos. Cards sem
     * evento também ficam fora. Para os demais perfis (allowedEventIds === null) não há restrição.
     */
    private function withinEventScope(User $user, Card $card): bool
    {
        $ids = $user->allowedEventIds();

        if ($ids === null) {
            return true;
        }

        return $card->event_id !== null && $ids->contains($card->event_id);
    }
}
