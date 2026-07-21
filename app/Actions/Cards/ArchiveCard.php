<?php

namespace App\Actions\Cards;

use App\Domain\Enums\MovementType;
use App\Models\Card;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ArchiveCard
{
    /**
     * Arquiva o card: ele deixa de aparecer no quadro Kanban, mas preserva
     * histórico, anexos e comentários para consulta posterior em "Todos os cards".
     */
    public function execute(Card $card, User $actor): Card
    {
        return DB::transaction(function () use ($card, $actor) {
            $card->update([
                'archived_at' => now(),
                'archived_by' => $actor->id,
            ]);

            $card->movements()->create([
                'user_id' => $actor->id,
                'from_board_id' => $card->board_id,
                'to_board_id' => null,
                'from_column_id' => $card->board_column_id,
                'to_column_id' => null,
                'type' => MovementType::Archival->value,
            ]);

            return $card->fresh();
        });
    }
}
