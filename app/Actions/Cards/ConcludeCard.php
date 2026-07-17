<?php

namespace App\Actions\Cards;

use App\Domain\Enums\MovementType;
use App\Models\Card;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConcludeCard
{
    /**
     * Marca o card como concluído: ele deixa de aparecer em qualquer quadro,
     * mas preserva histórico, anexos e comentários para consulta posterior.
     */
    public function execute(Card $card, User $actor): Card
    {
        return DB::transaction(function () use ($card, $actor) {
            $card->update([
                'concluded_at' => now(),
                'concluded_by' => $actor->id,
            ]);

            $card->movements()->create([
                'user_id' => $actor->id,
                'from_board_id' => $card->board_id,
                'to_board_id' => null,
                'from_column_id' => $card->board_column_id,
                'to_column_id' => null,
                'type' => MovementType::Conclusion->value,
            ]);

            return $card->fresh();
        });
    }
}
