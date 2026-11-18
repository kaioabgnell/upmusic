<?php

namespace App\Console\Commands;

use App\Actions\FixedCosts\GenerateFixedCostInstallments;
use App\Domain\Enums\FixedCostGenerationMode;
use App\Models\FixedCost;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Gera os cards das parcelas que entraram na janela "X dias antes do vencimento" (specs/27 §3.2).
 * Roda todo dia pelo agendador; o modo "todas agora" não passa por aqui — gera tudo ao salvar.
 */
class GenerateFixedCosts extends Command
{
    protected $signature = 'custos-fixos:gerar {--date= : Simula outro dia (AAAA-MM-DD)}';

    protected $description = 'Gera os cards das parcelas de custos fixos que entraram na janela de antecedência (ver specs/27).';

    public function handle(GenerateFixedCostInstallments $generate): int
    {
        $date = $this->option('date');

        if ($date !== null && (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1]))) {
            $this->error('Data inválida. Use o formato AAAA-MM-DD.');

            return self::FAILURE;
        }

        $today = $date ? CarbonImmutable::parse($date)->startOfDay() : CarbonImmutable::today();

        $created = 0;

        // Só os que ainda têm parcela sem card no cronograma.
        FixedCost::query()
            ->active()
            ->where('generation_mode', FixedCostGenerationMode::LeadDays->value)
            ->whereHas('installments', fn ($q) => $q->whereNull('generated_at'))
            ->chunkById(100, function ($costs) use ($generate, $today, &$created) {
                foreach ($costs as $cost) {
                    $created += $generate->execute($cost, null, $today);
                }
            });

        $this->info("{$created} card(s) de custo fixo gerado(s) em {$today->format('d/m/Y')}.");

        return self::SUCCESS;
    }
}
