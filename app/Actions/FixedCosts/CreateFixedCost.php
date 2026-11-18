<?php

namespace App\Actions\FixedCosts;

use App\Models\FixedCost;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Cadastro do custo fixo (specs/27 §2): monta o cronograma e já gera o que é devido agora (§3.1). */
class CreateFixedCost
{
    public function __construct(
        private SyncFixedCostSchedule $schedule,
        private GenerateFixedCostInstallments $generate,
    ) {}

    /** @return array{0: FixedCost, 1: int} o custo e quantos cards nasceram */
    public function execute(array $data, ?User $actor = null): array
    {
        $cost = DB::transaction(function () use ($data, $actor) {
            $cost = FixedCost::create($data + ['created_by' => $actor?->id]);
            $this->schedule->execute($cost);

            return $cost;
        });

        return [$cost, $this->generate->execute($cost, $actor)];
    }
}
