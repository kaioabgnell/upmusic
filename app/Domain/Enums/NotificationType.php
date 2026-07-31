<?php

namespace App\Domain\Enums;

/**
 * Tipos de notificação interna (specs/22). Novos eventos entram como novos casos + novo texto,
 * sem alterar o schema de `user_notifications`.
 */
enum NotificationType: string
{
    case CardAssigned = 'card_assigned';

    /**
     * Trecho central do texto exibido no painel. O nome do autor e o "#id - título" são
     * renderizados em <strong> pelo front, por isso não fazem parte deste label.
     */
    public function label(): string
    {
        return match ($this) {
            self::CardAssigned => 'te colocou como responsável do card',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::CardAssigned => 'fa-solid fa-user-check',
        };
    }
}
