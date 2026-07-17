<?php

namespace App\Actions\Cards;

use App\Domain\Enums\MovementType;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Card;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReopenCard
{
    /**
     * Reabre um card concluído, enviando-o para o quadro/coluna informado
     * (coluna de entrada por padrão). Passa a aparecer normalmente no Kanban.
     */
    public function execute(Card $card, Board $toBoard, ?BoardColumn $toColumn, User $actor): Card
    {
        return DB::transaction(function () use ($card, $toBoard, $toColumn, $actor) {
            $targetColumn = $toColumn
                ?? $toBoard->columns()->where('is_entry', true)->orderBy('position')->first()
                ?? $toBoard->columns()->orderBy('position')->first();

            $position = (int) $toBoard->cards()
                ->where('board_column_id', $targetColumn->id)
                ->max('position') + 1;

            $card->update([
                'board_id' => $toBoard->id,
                'board_column_id' => $targetColumn->id,
                'position' => $position,
                'concluded_at' => null,
                'concluded_by' => null,
            ]);

            $card->movements()->create([
                'user_id' => $actor->id,
                'from_board_id' => null,
                'to_board_id' => $toBoard->id,
                'from_column_id' => null,
                'to_column_id' => $targetColumn->id,
                'type' => MovementType::Reopening->value,
            ]);

            return $card->fresh();
        });
    }
}
