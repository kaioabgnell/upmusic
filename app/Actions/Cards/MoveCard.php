<?php

namespace App\Actions\Cards;

use App\Domain\Enums\MovementType;
use App\Models\BoardColumn;
use App\Models\Card;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MoveCard
{
    /**
     * Move o card para uma coluna/posição dentro do mesmo quadro e registra o histórico.
     */
    public function execute(Card $card, BoardColumn $toColumn, int $position, ?User $actor = null): Card
    {
        return DB::transaction(function () use ($card, $toColumn, $position, $actor) {
            $fromColumnId = $card->board_column_id;

            // Reindexa os cards da coluna de destino, inserindo o card na posição alvo.
            $siblings = $card->board->cards()
                ->where('board_column_id', $toColumn->id)
                ->where('id', '!=', $card->id)
                ->orderBy('position')
                ->pluck('id')
                ->all();

            $position = max(0, min($position, count($siblings)));
            array_splice($siblings, $position, 0, [$card->id]);

            foreach ($siblings as $index => $id) {
                $card->board->cards()->whereKey($id)->update([
                    'board_column_id' => $toColumn->id,
                    'position' => $index,
                ]);
            }

            $card->refresh();

            if ($fromColumnId !== $toColumn->id) {
                $card->movements()->create([
                    'user_id' => $actor?->id,
                    'from_board_id' => $card->board_id,
                    'to_board_id' => $card->board_id,
                    'from_column_id' => $fromColumnId,
                    'to_column_id' => $toColumn->id,
                    'type' => MovementType::Column->value,
                ]);
            }

            return $card;
        });
    }
}
