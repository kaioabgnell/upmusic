<?php

namespace App\Actions\FixedCosts;

use App\Actions\Cards\CreateCard;
use App\Domain\Enums\CardOrigin;
use App\Domain\Enums\FixedCostGenerationMode;
use App\Models\Board;
use App\Models\Card;
use App\Models\FixedCost;
use App\Models\FixedCostInstallment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Transforma as parcelas pendentes de um custo fixo em cards (specs/27 §3.1). É o ÚNICO caminho que
 * gera parcela: salvar o cadastro, renovar, reativar, o comando agendado e o botão "Gerar próxima
 * parcela agora" passam todos por aqui.
 *
 * Idempotente: a parcela é "reservada" com um UPDATE condicional (`generated_at` ainda nulo) antes de
 * criar o card. Rodar de novo não duplica, e numa corrida entre o comando e o botão o segundo
 * encontra a parcela já reservada e pula.
 */
class GenerateFixedCostInstallments
{
    public function __construct(private CreateCard $createCard) {}

    /** @return int quantos cards foram criados */
    public function execute(FixedCost $cost, ?User $actor = null, ?CarbonImmutable $today = null, bool $onlyNext = false): int
    {
        if (! $cost->active || $cost->trashed()) {
            return 0;
        }

        $board = $this->targetBoard($cost);
        if (! $board) {
            return 0;
        }

        $created = 0;
        foreach ($this->installmentsToGenerate($cost, $today, $onlyNext) as $installment) {
            if ($this->generate($cost, $board, $installment, $actor)) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * Parcelas que nasceriam agora, sem olhar se o custo está ativo — também serve para avisar,
     * antes de reativar ou trocar de modo, quantos cards serão criados.
     *
     * @return Collection<int,FixedCostInstallment>
     */
    public function installmentsToGenerate(FixedCost $cost, ?CarbonImmutable $today = null, bool $onlyNext = false): Collection
    {
        // Lê do banco: a relação em memória pode estar velha (ex.: renovação no mesmo request).
        $cost->unsetRelation('installments');
        $pending = $cost->pendingInstallments();

        if ($onlyNext) {
            return $pending->take(1)->values();
        }

        if ($cost->generation_mode === FixedCostGenerationMode::AllUpfront) {
            return $pending;
        }

        $today = ($today ?? CarbonImmutable::today())->startOfDay();

        // Inclui as atrasadas: vencimento que já passou ainda vira card, para a conta não sumir.
        return $pending
            ->filter(fn (FixedCostInstallment $i) => $i->due_date->subDays((int) $cost->lead_days)->lte($today))
            ->values();
    }

    /** @return Collection<int,int> */
    public function numbersToGenerate(FixedCost $cost, ?CarbonImmutable $today = null, bool $onlyNext = false): Collection
    {
        return $this->installmentsToGenerate($cost, $today, $onlyNext)->pluck('number');
    }

    /** Quadro de destino, se ainda puder receber parcelas (existe e continua sendo de custos fixos). */
    public function targetBoard(FixedCost $cost): ?Board
    {
        $board = Board::find($cost->board_id);

        return $board && $board->fixed_costs ? $board : null;
    }

    /** Card da parcela, com os dados do cadastro (título, empresa, valor previsto, vencimento). */
    public function createCardFor(FixedCost $cost, Board $board, FixedCostInstallment $installment, ?User $actor): Card
    {
        // Coluna do cadastro só vale se ainda for deste quadro; senão a CreateCard usa a de entrada.
        $columnId = $cost->board_column_id
            && $board->columns()->whereKey($cost->board_column_id)->exists()
                ? $cost->board_column_id
                : null;

        $card = $this->createCard->execute($board, [
            'board_column_id' => $columnId,
            'title' => $cost->name,
            'description' => $cost->description,
            'empresa_id' => $cost->empresa_id,
            'estimated_value' => $cost->installment_value,
            'due_date' => $installment->due_date->toDateString(),
            'fixed_cost_installment_id' => $installment->id,
        ], $actor, CardOrigin::FixedCost);

        $installment->update(['card_id' => $card->id]);

        return $card;
    }

    private function generate(FixedCost $cost, Board $board, FixedCostInstallment $installment, ?User $actor): bool
    {
        return DB::transaction(function () use ($cost, $board, $installment, $actor) {
            $claimed = FixedCostInstallment::whereKey($installment->id)
                ->whereNull('generated_at')
                ->update(['generated_at' => now(), 'generated_by' => $actor?->id]);

            if ($claimed === 0) {
                return false; // outro processo gerou esta parcela no meio do caminho
            }

            $this->createCardFor($cost, $board, $installment->refresh(), $actor);

            return true;
        });
    }
}
