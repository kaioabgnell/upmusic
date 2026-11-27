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
     * por evento (specs/28 §6) continua valendo como limite absoluto: ser responsável não fura a
     * restrição de eventos.
     */
    public function view(User $user, Card $card): bool
    {
        // Card de quadro de custos fixos (specs/27 §1.2) não abre por responsabilidade.
        $canRead = $user->canAccessBoard($card->board)
            || (! $card->board->fixed_costs && $card->assignee_id === $user->id);

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
     * Quem é restrito por evento (specs/28 §6) só enxerga/opera cards dos seus eventos. Card sem
     * evento também fica fora, exceto no quadro de custos fixos, onde os cards nunca têm evento.
     */
    private function withinEventScope(User $user, Card $card): bool
    {
        $ids = $user->allowedEventIds();

        if ($ids === null || $card->board?->fixed_costs) {
            return true;
        }

        return $card->event_id !== null && $ids->contains($card->event_id);
    }
}
