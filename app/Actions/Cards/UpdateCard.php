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
            $card->update([
                'title' => $data['title'] ?? $card->title,
                'description' => $data['description'] ?? null,
                'empresa_id' => $data['empresa_id'] ?? null,
                'fornecedor_id' => $data['fornecedor_id'] ?? null,
                'event_id' => $data['event_id'] ?? null,
                'assignee_id' => $data['assignee_id'] ?? null,
                'estimated_value' => $data['estimated_value'] ?? null,
                'actual_value' => $data['actual_value'] ?? null,
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
