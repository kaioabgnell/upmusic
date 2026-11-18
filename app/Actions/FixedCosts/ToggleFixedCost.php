<?php

namespace App\Actions\FixedCosts;

use App\Models\FixedCost;
use App\Models\User;

/**
 * Inativar pausa a geração; reativar gera na hora o que já entrou na janela, inclusive as parcelas
 * atrasadas (specs/27 §5.2). Os cards já gerados nunca mudam.
 */
class ToggleFixedCost
{
    public function __construct(private GenerateFixedCostInstallments $generate) {}

    /** @return int cards criados na reativação */
    public function execute(FixedCost $cost, ?User $actor = null): int
    {
        $cost->update(['active' => ! $cost->active]);

        return $cost->active ? $this->generate->execute($cost->fresh(), $actor) : 0;
    }
}
