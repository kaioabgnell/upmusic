<?php

namespace App\Actions\Prices;

use App\Models\Card;
use App\Models\PriceRecord;
use App\Models\User;

class SyncCardPriceRecord
{
    /**
     * Mantém um registro de preço (por categoria de fornecedor) espelhando o "valor realizado" do card.
     *
     * Regra (ver specs/15): se o card tem fornecedor com categoria E valor realizado > 0, cria/atualiza
     * um único registro (idempotente por card_id). Caso contrário, remove o registro daquele card.
     */
    public function execute(Card $card, ?User $actor = null): void
    {
        $card->loadMissing('fornecedor');

        $categoriaId = $card->fornecedor?->fornecedor_categoria_id;
        $actualValue = $card->actual_value !== null ? (float) $card->actual_value : null;

        // Sem fornecedor com categoria ou sem valor realizado: garante que não sobre registro órfão do card.
        if (! $categoriaId || $actualValue === null || $actualValue <= 0) {
            PriceRecord::where('card_id', $card->id)->delete();

            return;
        }

        $existing = PriceRecord::where('card_id', $card->id)->first();

        if ($existing) {
            // Mantém a reference_date original estável; só atualiza o que reflete o estado atual do card.
            $existing->update([
                'fornecedor_categoria_id' => $categoriaId,
                'fornecedor_id' => $card->fornecedor_id,
                'event_id' => $card->event_id,
                'price' => $actualValue,
            ]);

            return;
        }

        PriceRecord::create([
            'fornecedor_categoria_id' => $categoriaId,
            'fornecedor_id' => $card->fornecedor_id,
            'card_id' => $card->id,
            'event_id' => $card->event_id,
            'price' => $actualValue,
            'reference_date' => now()->toDateString(),
            'created_by' => $actor?->id,
        ]);
    }
}
