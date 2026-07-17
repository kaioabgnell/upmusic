<?php

namespace App\Actions\Templates;

use App\Actions\Cards\CreateCard;
use App\Domain\Enums\CardOrigin;
use App\Models\Board;
use App\Models\CardTemplate;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ImportTemplate
{
    public function __construct(private CreateCard $createCard) {}

    /**
     * Gera os cards do template no quadro informado. Colunas/campos incompatíveis
     * com o quadro de destino são ignorados (CreateCard filtra os campos válidos).
     *
     * @return int quantidade de cards criados
     */
    public function execute(CardTemplate $template, Board $board, ?Empresa $empresa, ?User $actor): int
    {
        $columnIds = $board->columns()->pluck('id')->all();

        return DB::transaction(function () use ($template, $board, $empresa, $actor, $columnIds) {
            $count = 0;

            foreach ($template->items()->orderBy('position')->get() as $item) {
                $data = [
                    'title' => $item->title,
                    'description' => $item->description,
                    'empresa_id' => $empresa?->id,
                    'due_date' => $item->due_date?->format('Y-m-d'),
                    'priority' => $item->priority?->value,
                    'assignee_id' => $item->default_assignee_id,
                    'fields' => $item->default_fields ?? [],
                ];

                // Só aplica a coluna padrão se ela pertencer ao quadro de destino.
                if ($item->default_column_id && in_array($item->default_column_id, $columnIds, true)) {
                    $data['board_column_id'] = $item->default_column_id;
                }

                $this->createCard->execute($board, $data, $actor, CardOrigin::Template);
                $count++;
            }

            return $count;
        });
    }
}
