<?php

namespace App\Support;

use App\Models\Card;

class CardPresenter
{
    /**
     * Shape compacto do card, usado no Kanban/Lista do quadro. Reaproveitado tanto na leitura
     * (endpoint de dados do quadro) quanto nas respostas de criação/edição, para que o card devolvido
     * tenha sempre o mesmo formato — sem isso, o front precisaria de dois formatos distintos para o
     * mesmo objeto.
     */
    public static function compact(Card $card): array
    {
        $card->loadMissing(['empresa:id,corporate_name', 'event:id,name', 'assignee:id,name,avatar_path']);

        if (! array_key_exists('attachments_count', $card->getAttributes())) {
            $card->loadCount(['attachments', 'comments']);
        }

        return [
            'id' => $card->id,
            'board_column_id' => $card->board_column_id,
            'title' => $card->title,
            'empresa' => $card->empresa?->corporate_name,
            'event' => $card->event?->name,
            'assignee' => $card->assignee?->name,
            'assignee_initial' => $card->assignee?->initials(),
            'assignee_avatar_url' => $card->assignee?->avatar_url,
            'due_date' => $card->due_date?->format('d/m/Y'),
            'due_status' => match (true) {
                $card->due_date?->isToday() => 'today',
                $card->due_date?->isPast() => 'overdue',
                $card->due_date?->isTomorrow() => 'tomorrow',
                default => null,
            },
            'priority' => $card->priority->value,
            'estimated_value' => $card->estimated_value,
            'attachments_count' => $card->attachments_count,
            'comments_count' => $card->comments_count,
        ];
    }
}
