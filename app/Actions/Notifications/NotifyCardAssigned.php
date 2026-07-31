<?php

namespace App\Actions\Notifications;

use App\Domain\Enums\NotificationType;
use App\Models\Card;
use App\Models\User;
use App\Models\UserNotification;

/**
 * Cria a notificação de "te colocou como responsável do card" (specs/22). Chamada pelo
 * `CardObserver`, que é o único gatilho — assim qualquer caminho que grave `assignee_id`
 * (criação, edição, duplicação, import de template) fica coberto por construção.
 */
class NotifyCardAssigned
{
    public function execute(Card $card, int $assigneeId): ?UserNotification
    {
        $actor = auth()->user();

        // Auto-atribuição não gera aviso — a pessoa acabou de fazer a ação.
        if ($actor && $actor->id === $assigneeId) {
            return null;
        }

        // Usuário inativo/removido não acumula notificação.
        if (! User::where('active', true)->whereKey($assigneeId)->exists()) {
            return null;
        }

        return UserNotification::create([
            'user_id' => $assigneeId,
            'actor_id' => $actor?->id,
            'card_id' => $card->id,
            'board_id' => $card->board_id,
            'type' => NotificationType::CardAssigned,
            // Snapshot: a notificação descreve o fato como ele foi, mesmo que o card mude de
            // título depois (mesmo princípio de `card_movements`).
            'data' => [
                'card_title' => $card->title,
                'actor_name' => $actor?->name,
            ],
        ]);
    }
}
