<?php

namespace App\Actions\Cards;

use App\Actions\Prices\SyncCardPriceRecord;
use App\Models\Card;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateCard
{
    use SyncsCardFields;

    public function __construct(private SyncCardPriceRecord $syncPriceRecord) {}

    public function execute(Card $card, array $data, ?User $actor = null): Card
    {
        return DB::transaction(function () use ($card, $data, $actor) {
            $unitValue = $data['unit_value'] ?? null;
            $quantity = $data['quantity'] ?? 1;

            $card->update([
                'title' => $data['title'] ?? $card->title,
                'description' => $data['description'] ?? null,
                'empresa_id' => $data['empresa_id'] ?? null,
                'fornecedor_id' => $data['fornecedor_id'] ?? null,
                'event_id' => $data['event_id'] ?? null,
                'assignee_id' => $data['assignee_id'] ?? null,
                'estimated_value' => $data['estimated_value'] ?? null,
                'unit_value' => $unitValue,
                'quantity' => $quantity,
                // Total realizado é DERIVADO do unitário, como na planilha do Financeiro
                // (specs/23 §2: TOTAL = unitário × quantidade). Card sem unitário mantém o total
                // informado à mão, para não zerar o histórico de quem ainda não usa o unitário.
                'actual_value' => $unitValue !== null ? $unitValue * $quantity : ($data['actual_value'] ?? null),
                'valor_sem_nota' => $data['valor_sem_nota'] ?? null,
                'valor_com_nota' => $data['valor_com_nota'] ?? null,
                'negociado' => $data['negociado'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'priority' => $data['priority'] ?? $card->priority->value,
            ]);

            if (isset($data['fields']) && is_array($data['fields'])) {
                $this->syncFieldValues($card, $data['fields']);
            }

            // Espelha o valor realizado no banco de preços da categoria do fornecedor (specs/15).
            $this->syncPriceRecord->execute($card->fresh(), $actor);

            return $card->fresh();
        });
    }
}
