<?php

namespace App\Actions\FixedCosts;

use App\Models\FixedCost;
use App\Models\FixedCostInstallment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Exclui uma parcela do cronograma (specs/27 §5.5) e renumera as seguintes: num aluguel de 12, excluir
 * a 4 faz a 5 virar a 4… e o total passar para 11. Os vencimentos das seguintes não mudam (a de maio
 * continua em maio). O card da parcela, se houver, vai para a lixeira junto.
 *
 * Parcela paga (card concluído ou na etapa final) não sai, e a única parcela também não — aí o caminho
 * é inativar ou excluir o custo fixo.
 */
class DeleteFixedCostInstallment
{
    public function execute(FixedCost $cost, FixedCostInstallment $installment): void
    {
        $installment->loadMissing(['card' => fn ($q) => $q->with('column:id,is_final')]);

        if ($installment->isPaid()) {
            throw ValidationException::withMessages([
                'installment' => "A parcela {$installment->number} já foi paga e não pode ser excluída.",
            ]);
        }

        if ($cost->installments()->count() <= 1) {
            throw ValidationException::withMessages([
                'installment' => 'Esta é a única parcela. Inative ou exclua o custo fixo.',
            ]);
        }

        DB::transaction(function () use ($cost, $installment) {
            if ($installment->card && ! $installment->card->trashed()) {
                $installment->card->delete();
            }

            $number = $installment->number;
            $installment->delete();

            // Uma a uma, em ordem crescente: o índice único (custo, número) não aceita duas linhas com
            // o mesmo número nem por um instante.
            $cost->installments()->where('number', '>', $number)->get()
                ->each(fn (FixedCostInstallment $i) => $i->update(['number' => $i->number - 1]));

            $cost->update(['installments_total' => $cost->installments_total - 1]);
            $cost->unsetRelation('installments');
        });
    }
}
