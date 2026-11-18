<?php

namespace App\Actions\FixedCosts;

use App\Models\FixedCost;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Renovar (specs/27 §5.4): soma parcelas ao total — 12 + 12 = 24. A próxima é a 13, vencendo no mês
 * seguinte ao da última. O selo lê o total do cadastro, então os cards anteriores passam a mostrar "de 24".
 */
class RenewFixedCost
{
    public function __construct(
        private SyncFixedCostSchedule $schedule,
        private GenerateFixedCostInstallments $generate,
    ) {}

    /** @return int cards criados na hora (modo "todas agora", ou parcelas já dentro da janela) */
    public function execute(FixedCost $cost, int $extra, ?User $actor = null): int
    {
        DB::transaction(function () use ($cost, $extra) {
            $cost->update(['installments_total' => $cost->installments_total + $extra]);
            $this->schedule->execute($cost);
        });

        return $this->generate->execute($cost->fresh(), $actor);
    }
}
