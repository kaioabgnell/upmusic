<?php

namespace App\Actions\FixedCosts;

use App\Models\FixedCost;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Edição do custo fixo (specs/27 §5.1). Nome, descrição, empresa e valor valem para as parcelas
 * ainda não geradas; com `$propagate`, também para os cards já gerados que seguem em aberto (nem
 * concluídos, nem arquivados, nem excluídos). Quadro e coluna só valem para as próximas parcelas.
 */
class UpdateFixedCost
{
    public function __construct(
        private SyncFixedCostSchedule $schedule,
        private GenerateFixedCostInstallments $generate,
    ) {}

    /** @return array{propagated:int, created:int} */
    public function execute(FixedCost $cost, array $data, bool $propagate = false, ?User $actor = null): array
    {
        $propagated = DB::transaction(function () use ($cost, $data, $propagate) {
            // Começar na parcela e primeiro vencimento travam depois da 1ª geração: mudar qualquer um
            // dos dois recalcularia o vencimento das parcelas que já viraram card.
            $locked = $cost->hasGeneratedInstallments();
            if ($locked) {
                unset($data['start_installment'], $data['first_due_date']);
            }

            $cost->update($data);

            // Sem nada gerado, mudar o início ou o primeiro vencimento refaz o cronograma; nos demais
            // casos ele só cresce ou encolhe no fim, preservando os vencimentos (e as exclusões) já feitos.
            $this->schedule->execute($cost, rebuild: ! $locked && $cost->wasChanged(['start_installment', 'first_due_date']));

            return $propagate ? $this->propagate($cost) : 0;
        });

        $created = $this->generate->execute($cost->fresh(), $actor);

        return ['propagated' => $propagated, 'created' => $created];
    }

    private function propagate(FixedCost $cost): int
    {
        $count = 0;

        $cost->installments()
            ->whereNotNull('generated_at')
            ->with(['card' => fn ($q) => $q->withoutTrashed()])
            ->get()
            ->pluck('card')
            ->filter(fn ($card) => $card && ! $card->concluded_at && ! $card->archived_at)
            ->each(function ($card) use ($cost, &$count) {
                $card->update([
                    'title' => $cost->name,
                    'description' => $cost->description,
                    'empresa_id' => $cost->empresa_id,
                    'estimated_value' => $cost->installment_value,
                ]);
                $count++;
            });

        return $count;
    }
}
