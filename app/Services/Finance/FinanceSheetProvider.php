<?php

namespace App\Services\Finance;

use App\Domain\Enums\FinanceRevenueCategory;
use App\Models\Event;
use App\Models\FinanceSheet;
use Illuminate\Support\Facades\DB;

/**
 * Resolve (ou cria) a planilha financeira de um evento — specs/23 §4.1.
 *
 * Não existe "criar planilha" na UI: ela nasce na primeira vez que alguém abre o financeiro do
 * evento ou que um card é enviado ao financeiro.
 */
class FinanceSheetProvider
{
    public function forEvent(Event $event): FinanceSheet
    {
        $sheet = FinanceSheet::where('event_id', $event->id)->first();

        if ($sheet) {
            return $sheet;
        }

        return DB::transaction(function () use ($event) {
            // firstOrCreate dentro da transação: dois cliques simultâneos não criam duas planilhas
            // (o unique em event_id é a garantia final).
            $sheet = FinanceSheet::firstOrCreate(['event_id' => $event->id]);

            if ($sheet->wasRecentlyCreated) {
                $this->seedRevenues($sheet);
            }

            return $sheet;
        });
    }

    /**
     * Semeia as linhas fixas da aba RECEITAS, na ordem do arquivo modelo, com valores zerados —
     * o usuário abre a aba e encontra a estrutura conhecida, em vez de uma tela vazia.
     * `Patrocínio` fica de fora: é o único que se repete N vezes (um por patrocinador) e entra
     * sob demanda.
     */
    private function seedRevenues(FinanceSheet $sheet): void
    {
        $now = now();

        $sheet->revenues()->insert(collect(FinanceRevenueCategory::seedRows())
            ->values()
            ->map(fn (FinanceRevenueCategory $category, int $i) => [
                'finance_sheet_id' => $sheet->id,
                'category' => $category->value,
                'position' => $i,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
    }
}
