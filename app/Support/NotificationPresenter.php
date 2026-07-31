<?php

namespace App\Support;

use App\Models\UserNotification;

/**
 * Shape único da notificação enviada ao painel do sino (specs/22 §4.4) — mesmo padrão de
 * `CardPresenter`.
 */
class NotificationPresenter
{
    public static function item(UserNotification $n): array
    {
        // Igual ao CardPresenter: garante o autor carregado mesmo quando o chamador não fez o
        // eager loading (Model::preventLazyLoading está ligado fora de produção).
        $n->loadMissing('actor:id,name,avatar_path');

        return [
            'id' => $n->id,
            'type' => $n->type->value,
            'icon' => $n->type->icon(),
            // Sem autor (formulário externo, captura, usuário removido) o aviso é do "Sistema".
            'actor_name' => $n->actor?->name ?? ($n->data['actor_name'] ?? 'Sistema'),
            'actor_initials' => $n->actor?->initials(),
            'actor_avatar_url' => $n->actor?->avatar_url,
            'action_text' => $n->type->label(),
            'card_label' => '#'.$n->card_id.' - '.($n->data['card_title'] ?? ''),
            'url' => route('boards.show.card', ['board' => $n->board_id, 'card' => $n->card_id]),
            'is_read' => $n->read_at !== null,
            'created_at_human' => $n->created_at->diffForHumans(),
            'created_at_full' => $n->created_at->format('d/m/Y H:i'),
        ];
    }
}
