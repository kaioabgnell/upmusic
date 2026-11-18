<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cronograma completo de parcelas (specs/27 §2.3). Até aqui, `fixed_cost_installments` só guardava as
 * parcelas já geradas e o vencimento das demais era calculado pelo número. Com a exclusão de parcela
 * (que renumera as seguintes sem mexer nos vencimentos), o vencimento deixa de ser função do número:
 * toda parcela passa a ter linha, e `generated_at` nulo = ainda sem card.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE fixed_cost_installments MODIFY generated_at TIMESTAMP NULL');

        // Completa o cronograma dos custos já cadastrados (sem exclusões até aqui, a fórmula vale).
        $now = now();

        foreach (DB::table('fixed_costs')->get() as $cost) {
            $existing = DB::table('fixed_cost_installments')->where('fixed_cost_id', $cost->id)->pluck('number')->flip();
            $first = CarbonImmutable::parse($cost->first_due_date);
            $rows = [];

            for ($n = $cost->start_installment; $n <= $cost->installments_total; $n++) {
                if ($existing->has($n)) {
                    continue;
                }

                $rows[] = [
                    'fixed_cost_id' => $cost->id,
                    'number' => $n,
                    'due_date' => $first->addMonthsNoOverflow($n - $cost->start_installment)->toDateString(),
                    'card_id' => null,
                    'generated_at' => null,
                    'generated_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($rows) {
                DB::table('fixed_cost_installments')->insert($rows);
            }
        }
    }

    public function down(): void
    {
        DB::table('fixed_cost_installments')->whereNull('generated_at')->delete();
        DB::statement('ALTER TABLE fixed_cost_installments MODIFY generated_at TIMESTAMP NOT NULL');
    }
};
