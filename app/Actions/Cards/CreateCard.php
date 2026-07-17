<?php

namespace App\Actions\Cards;

use App\Actions\Prices\SyncCardPriceRecord;
use App\Domain\Enums\CardOrigin;
use App\Models\Board;
use App\Models\Card;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateCard
{
    use SyncsCardFields;

    public function __construct(private SyncCardPriceRecord $syncPriceRecord) {}

    /**
     * Cria um card no quadro. $data aceita chaves fixas + 'fields' (campos configuráveis).
     */
    public function execute(Board $board, array $data, ?User $actor = null, CardOrigin $origin = CardOrigin::Manual): Card
    {
        return DB::transaction(function () use ($board, $data, $actor, $origin) {
            $columnId = $data['board_column_id']
                ?? $board->columns()->where('is_entry', true)->value('id')
                ?? $board->columns()->orderBy('position')->value('id');

            $position = (int) $board->cards()
                ->where('board_column_id', $columnId)
                ->max('position') + 1;

            $card = $board->cards()->create([
                'board_column_id' => $columnId,
                'empresa_id' => $data['empresa_id'] ?? null,
                'fornecedor_id' => $data['fornecedor_id'] ?? null,
                'event_id' => $data['event_id'] ?? null,
                'assignee_id' => $data['assignee_id'] ?? null,
                'created_by' => $actor?->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'estimated_value' => $data['estimated_value'] ?? null,
                'actual_value' => $data['actual_value'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'priority' => $data['priority'] ?? 'media',
                'origin' => $origin->value,
                'position' => $position,
            ]);

            if (! empty($data['fields']) && is_array($data['fields'])) {
                $this->syncFieldValues($card, $data['fields']);
            }

            // Espelha o valor realizado no banco de preços da categoria do fornecedor (specs/15).
            $this->syncPriceRecord->execute($card, $actor);

            return $card;
        });
    }
}
