<?php

namespace App\Actions\Finance;

use App\Domain\Enums\FinanceSheetStatus;
use App\Models\FinanceSheet;
use App\Models\User;

/**
 * Fecha (ou reabre) a prestação de contas do evento — specs/23 §8.5.
 * Fechada, a planilha vira somente leitura e anexo vinculado não pode mais ser excluído no card.
 */
class CloseFinanceSheet
{
    public function close(FinanceSheet $sheet, User $actor): FinanceSheet
    {
        $sheet->update([
            'status' => FinanceSheetStatus::Fechado,
            'closed_at' => now(),
            'closed_by' => $actor->id,
        ]);

        return $sheet;
    }

    public function reopen(FinanceSheet $sheet): FinanceSheet
    {
        $sheet->update([
            'status' => FinanceSheetStatus::Aberto,
            'closed_at' => null,
            'closed_by' => null,
        ]);

        return $sheet;
    }
}
