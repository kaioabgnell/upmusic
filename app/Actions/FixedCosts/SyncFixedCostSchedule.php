<?php

namespace App\Actions\FixedCosts;

use App\Models\FixedCost;
use App\Models\FixedCostInstallment;

/**
 * Mantém o cronograma de parcelas (specs/27 §2.3) com uma linha por parcela, de "começar na parcela"
 * até o total. Chamado ao cadastrar, editar e renovar.
 *
 * - Total maior: acrescenta parcelas no fim, mês a mês depois da última (contando os meses a partir do
 *   primeiro vencimento, para o dia 31 não "encolher").
 * - Total menor: remove do fim as parcelas ainda não geradas (o Form Request já impede ficar abaixo da
 *   maior parcela gerada).
 * - `$rebuild`: refaz tudo — só quando nada foi gerado e mudou o início ou o primeiro vencimento.
 */
class SyncFixedCostSchedule
{
    public function execute(FixedCost $cost, bool $rebuild = false): void
    {
        if ($rebuild) {
            $cost->installments()->whereNull('generated_at')->delete();
        }

        $cost->installments()
            ->whereNull('generated_at')
            ->where('number', '>', $cost->installments_total)
            ->delete();

        $last = $cost->installments()->reorder()->orderByDesc('number')->first();
        $number = $last ? $last->number + 1 : $cost->start_installment;
        $offset = $last ? $cost->offsetOf($last->due_date) + 1 : 0;

        $rows = [];
        for (; $number <= $cost->installments_total; $number++, $offset++) {
            $rows[] = [
                'fixed_cost_id' => $cost->id,
                'number' => $number,
                'due_date' => $cost->dueDateForOffset($offset)->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows) {
            FixedCostInstallment::insert($rows);
        }

        $cost->unsetRelation('installments');
    }
}
