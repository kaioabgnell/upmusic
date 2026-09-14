<?php

namespace App\Actions\Prices;

use App\Models\Card;
use App\Models\PriceRecord;
use App\Models\User;

class SyncCardPriceRecord
{
    /**
     * Mantém um registro de preço (por categoria de fornecedor) espelhando o valor realizado do card.
     *
     * Regra (ver specs/15): se o card tem fornecedor com categoria E valor realizado > 0, cria/atualiza
     * um único registro (idempotente por card_id). Caso contrário, remove o registro daquele card.
     *
     * O preço guardado é o UNITÁRIO do card, não o total contratado: a categoria declara a unidade
     * (diária, hora, unidade) e tem um Preço Interno por unidade, então só o unitário é comparável
     * entre eventos — "a diária de limpeza custou 290 aqui e 280 lá" só faz sentido por unidade;
     * o total varia com o tamanho da contratação e não diz nada sobre o preço do fornecedor.
     * Card sem unitário (os antigos, os do formulário externo e os da captura rápida) continua
     * valendo pelo total, que nesses casos é de uma unidade só.
     */
    public function execute(Card $card, ?User $actor = null): void
    {
        $card->loadMissing('fornecedor');

        $categoriaId = $card->fornecedor?->fornecedor_categoria_id;
        $unitPrice = $card->unit_value ?? $card->actual_value;
        $unitPrice = $unitPrice !== null ? (float) $unitPrice : null;

        // Sem fornecedor com categoria ou sem valor realizado: garante que não sobre registro órfão do card.
        if (! $categoriaId || $unitPrice === null || $unitPrice <= 0) {
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
                'price' => $unitPrice,
            ]);

            return;
        }

        PriceRecord::create([
            'fornecedor_categoria_id' => $categoriaId,
            'fornecedor_id' => $card->fornecedor_id,
            'card_id' => $card->id,
            'event_id' => $card->event_id,
            'price' => $unitPrice,
            'reference_date' => now()->toDateString(),
            'created_by' => $actor?->id,
        ]);
    }
}
