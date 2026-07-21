<?php

namespace App\Actions\Cards;

use App\Domain\Enums\MovementType;
use App\Models\Card;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RejectCard
{
    /**
     * Reprova o card na coluna atual: arquiva (mesmos campos usados pelo Arquivar manual — ver
     * ArchiveCard), guardando o motivo obrigatório na movimentação para diferenciar no histórico
     * de um arquivamento manual comum (specs/17).
     */
    public function execute(Card $card, User $actor, string $reason): Card
    {
        return DB::transaction(function () use ($card, $actor, $reason) {
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
                'type' => MovementType::Rejection->value,
                'note' => $reason,
            ]);

            return $card->fresh();
        });
    }
}
