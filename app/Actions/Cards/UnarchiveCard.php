<?php

namespace App\Actions\Cards;

use App\Domain\Enums\MovementType;
use App\Models\Card;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UnarchiveCard
{
    /**
     * Desarquiva o card, restaurando-o no quadro/coluna em que já estava
     * (diferente da reabertura de conclusão, não há escolha de destino).
     */
    public function execute(Card $card, User $actor): Card
    {
        return DB::transaction(function () use ($card, $actor) {
            $card->update([
                'archived_at' => null,
                'archived_by' => null,
            ]);

            $card->movements()->create([
                'user_id' => $actor->id,
                'from_board_id' => null,
                'to_board_id' => $card->board_id,
                'from_column_id' => null,
                'to_column_id' => $card->board_column_id,
                'type' => MovementType::Unarchival->value,
            ]);

            return $card->fresh();
        });
    }
}
