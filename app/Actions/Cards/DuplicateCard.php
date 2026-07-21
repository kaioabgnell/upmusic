<?php

namespace App\Actions\Cards;

use App\Models\Card;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DuplicateCard
{
    public function __construct(private CreateCard $createCard) {}

    /**
     * Duplica o card na mesma coluna do original, com " [CÓPIA]" ao final do título.
     * Copia os campos fixos e os valores dos campos configuráveis; não copia anexos,
     * comentários, histórico de movimentações ou conclusão/arquivamento.
     */
    public function execute(Card $card, User $actor): Card
    {
        return DB::transaction(function () use ($card, $actor) {
            $suffix = ' [CÓPIA]';
            $title = Str::limit($card->title, 180 - mb_strlen($suffix), '').$suffix;

            $fields = $card->fieldValues->mapWithKeys(fn ($v) => [$v->board_field_id => $v->value])->all();

            return $this->createCard->execute($card->board, [
                'board_column_id' => $card->board_column_id,
                'empresa_id' => $card->empresa_id,
                'fornecedor_id' => $card->fornecedor_id,
                'event_id' => $card->event_id,
                'assignee_id' => $card->assignee_id,
                'title' => $title,
                'description' => $card->description,
                'estimated_value' => $card->estimated_value,
                'actual_value' => $card->actual_value,
                'due_date' => $card->due_date?->format('Y-m-d'),
                'priority' => $card->priority->value,
                'fields' => $fields,
            ], $actor, $card->origin);
        });
    }
}
