<?php

namespace App\Policies;

use App\Models\Board;
use App\Models\Card;
use App\Models\User;

class CardPolicy
{
    // Admin liberado via Gate::before.

    public function view(User $user, Card $card): bool
    {
        return $user->canAccessBoard($card->board) && $this->withinEventScope($user, $card);
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
