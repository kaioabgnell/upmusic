<?php

namespace App\Actions\Cards;

use App\Models\Card;

trait SyncsCardFields
{
    /**
     * Sincroniza os valores dos campos configuráveis do quadro.
     *
     * @param  array<int, mixed>  $fields  [board_field_id => value]
     */
    protected function syncFieldValues(Card $card, array $fields): void
    {
        $validIds = $card->board->fields()->pluck('id')->all();

        foreach ($fields as $fieldId => $value) {
            $fieldId = (int) $fieldId;
            if (! in_array($fieldId, $validIds, true)) {
                continue;
            }

            if (is_array($value)) {
                $value = implode(', ', $value);
            }

            $card->fieldValues()->updateOrCreate(
                ['board_field_id' => $fieldId],
                ['value' => $value === '' ? null : $value],
            );
        }
    }
}
