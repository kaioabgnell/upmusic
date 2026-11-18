<?php

namespace App\Actions\FixedCosts;

use App\Models\Card;
use App\Models\FixedCost;
use App\Models\FixedCostInstallment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Gerar card novamente" (specs/27 §3.4): a parcela foi gerada e o card excluído depois. A geração
 * automática nunca recria esse card; aqui a pessoa pede explicitamente um card novo para a parcela.
 */
class RegenerateInstallmentCard
{
    public function __construct(private GenerateFixedCostInstallments $generate) {}

    public function execute(FixedCost $cost, FixedCostInstallment $installment, ?User $actor = null): Card
    {
        $installment->loadMissing('card');

        if (! $installment->cardDeleted()) {
            throw ValidationException::withMessages([
                'installment' => "A parcela {$installment->number} já tem um card ativo.",
            ]);
        }

        $board = $this->generate->targetBoard($cost);
        if (! $board) {
            throw ValidationException::withMessages([
                'installment' => 'O quadro de destino não recebe mais custos fixos. Troque o quadro no cadastro.',
            ]);
        }

        return DB::transaction(function () use ($cost, $board, $installment, $actor) {
            $card = $this->generate->createCardFor($cost, $board, $installment, $actor);

            // O card antigo (na lixeira) deixa de apontar para a parcela: o selo é só do card novo.
            Card::withTrashed()
                ->where('fixed_cost_installment_id', $installment->id)
                ->whereKeyNot($card->id)
                ->update(['fixed_cost_installment_id' => null]);

            return $card;
        });
    }
}
