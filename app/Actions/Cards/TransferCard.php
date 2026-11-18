<?php

namespace App\Actions\Cards;

use App\Domain\Enums\MovementType;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Card;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferCard
{
    /**
     * Transfere o card para outro quadro/departamento (coluna de entrada por padrão),
     * preservando histórico, anexos, comentários e valores. Registra o movimento.
     */
    public function execute(Card $card, Board $toBoard, ?BoardColumn $toColumn = null, ?User $actor = null): Card
    {
        self::ensureSameBoardType($card, $toBoard);

        return DB::transaction(function () use ($card, $toBoard, $toColumn, $actor) {
            $fromBoardId = $card->board_id;
            $fromColumnId = $card->board_column_id;

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
            ]);

            $card->movements()->create([
                'user_id' => $actor?->id,
                'from_board_id' => $fromBoardId,
                'to_board_id' => $toBoard->id,
                'from_column_id' => $fromColumnId,
                'to_column_id' => $targetColumn->id,
                'type' => MovementType::Board->value,
            ]);

            return $card->fresh();
        });
    }

    /**
     * Quadros de custos fixos e quadros de eventos não trocam cards, em nenhum sentido (specs/27 §1.3).
     */
    public static function ensureSameBoardType(Card $card, Board $toBoard): void
    {
        $from = (bool) Board::whereKey($card->board_id)->withTrashed()->value('fixed_costs');

        if ($from !== (bool) $toBoard->fixed_costs) {
            throw ValidationException::withMessages([
                'board_id' => $from
                    ? 'Cards de custos fixos não podem ir para quadros de eventos.'
                    : 'Cards de eventos não podem ir para quadros de custos fixos.',
            ]);
        }
    }
}
