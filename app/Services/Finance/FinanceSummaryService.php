<?php

namespace App\Services\Finance;

use App\Domain\Enums\FinanceCostStatus;
use App\Models\FinanceCostItem;
use App\Models\FinanceDocument;
use App\Models\FinancePayment;
use App\Models\FinanceRevenue;
use App\Models\FinanceSheet;
use Illuminate\Support\Facades\DB;

/**
 * Agregações do RESUMO GERAL (specs/23 §7). Tudo sai do banco em SUM/GROUP BY — nada de laço em
 * Blade. Ao contrário do arquivo Excel, nenhuma célula do resumo tem armazenamento próprio: no
 * modelo as fórmulas apontam para intervalos fixos (`CUSTOS!F214`, `=#REF!`) que passam a somar a
 * faixa errada assim que alguém insere uma linha.
 */
class FinanceSummaryService
{
    /** @return array<string,mixed> */
    public function summary(FinanceSheet $sheet): array
    {
        $revenue = $this->revenueTotals($sheet);
        $cost = $this->costTotals($sheet);
        $paid = (float) FinancePayment::whereIn(
            'finance_cost_item_id',
            FinanceCostItem::where('finance_sheet_id', $sheet->id)->select('id')
        )->sum('amount');

        $currentEstimate = $sheet->uses_second_estimate ? $cost['current'] : $cost['estimated_1'];

        return [
            'revenue' => $revenue,
            'cost' => $cost + ['current_estimate' => $currentEstimate],
            'result' => [
                'estimated' => $revenue['estimated'] - $currentEstimate,
                'actual' => $revenue['actual'] - $cost['actual'],
            ],
            'progress' => [
                'paid' => $paid,
                'pending' => $cost['actual'] - $paid,
                'pct' => $cost['actual'] > 0 ? round($paid / $cost['actual'] * 100, 1) : null,
            ],
            'deviation' => $this->deviation($currentEstimate, $cost['actual']),
        ];
    }

    /** @return array{estimated:float,actual:float,received:float,pending:float} */
    public function revenueTotals(FinanceSheet $sheet): array
    {
        $row = FinanceRevenue::where('finance_sheet_id', $sheet->id)
            ->selectRaw('COALESCE(SUM(estimated_value),0) est, COALESCE(SUM(actual_value),0) act,
                         COALESCE(SUM(received_value),0) rec, COALESCE(SUM(pending_value),0) pend')
            ->first();

        return [
            'estimated' => (float) $row->est,
            'actual' => (float) $row->act,
            'received' => (float) $row->rec,
            'pending' => (float) $row->pend,
        ];
    }

    /** @return array{estimated_1:float,estimated_2:float,current:float,actual:float,count:int} */
    public function costTotals(FinanceSheet $sheet): array
    {
        $row = FinanceCostItem::where('finance_sheet_id', $sheet->id)
            ->selectRaw(sprintf(
                'COALESCE(SUM(total_estimated_1),0) e1, COALESCE(SUM(total_estimated_2),0) e2, '
                .'COALESCE(SUM(%s),0) cur, COALESCE(SUM(total_actual),0) act, COUNT(*) total',
                FinanceCostItem::currentEstimateSql(),
            ))
            ->first();

        return [
            'estimated_1' => (float) $row->e1,
            'estimated_2' => (float) $row->e2,
            'current' => (float) $row->cur,
            'actual' => (float) $row->act,
            'count' => (int) $row->total,
        ];
    }

    /**
     * "CUSTO POR ITEM" do resumo: previsto x realizado por categoria de fornecedor.
     *
     * @return array<int,array<string,mixed>>
     */
    public function byCategory(FinanceSheet $sheet): array
    {
        $rows = FinanceCostItem::where('finance_cost_items.finance_sheet_id', $sheet->id)
            ->leftJoin('fornecedor_categorias as fc', 'fc.id', '=', 'finance_cost_items.fornecedor_categoria_id')
            ->selectRaw(sprintf(
                "COALESCE(fc.nome, 'Sem categoria') as label, SUM(total_estimated_1) e1, SUM(%s) cur, SUM(total_actual) act",
                FinanceCostItem::currentEstimateSql(),
            ))
            ->groupBy('label')
            ->orderByDesc('cur')
            ->get();

        return $rows->map(fn ($r) => [
            'label' => $r->label,
            'estimated_1' => (float) $r->e1,
            'estimated' => (float) $r->cur,
            'actual' => (float) $r->act,
        ] + $this->deviation((float) $r->cur, (float) $r->act))->all();
    }

    /**
     * "PAGO POR": quebra do que já foi pago por grupo de pagamento.
     *
     * @return array<int,array<string,mixed>>
     */
    public function byPaymentSource(FinanceSheet $sheet): array
    {
        return FinancePayment::whereIn(
            'finance_payments.finance_cost_item_id',
            FinanceCostItem::where('finance_sheet_id', $sheet->id)->select('id')
        )
            ->join('finance_payment_sources as s', 's.id', '=', 'finance_payments.finance_payment_source_id')
            ->selectRaw('s.name as label, s.kind as kind, SUM(finance_payments.amount) total')
            ->groupBy('s.id', 's.name', 's.kind')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => ['label' => $r->label, 'kind' => $r->kind, 'total' => (float) $r->total])
            ->all();
    }

    /**
     * Alertas de consistência (specs/23 §8.2) — o que a planilha nunca avisou.
     *
     * @return array<int,array{icon:string,text:string,filter:array<string,string>}>
     */
    public function alerts(FinanceSheet $sheet): array
    {
        $items = FinanceCostItem::where('finance_sheet_id', $sheet->id);

        $withoutProof = (clone $items)
            ->whereNotNull('unit_actual')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('finance_documents')
                ->whereColumn('finance_documents.finance_cost_item_id', 'finance_cost_items.id')
                ->where('finance_documents.kind', 'comprovante'))
            ->count();

        $missingInvoice = (clone $items)->where('status', FinanceCostStatus::ContratoFaltaNota->value)->count();

        $overpaid = (clone $items)
            ->whereRaw('total_actual < (select COALESCE(SUM(amount),0) from finance_payments
                        where finance_payments.finance_cost_item_id = finance_cost_items.id)')
            ->count();

        $percentage = (float) $sheet->settlements()->sum('percentage');

        $alerts = [];

        if ($withoutProof > 0) {
            $alerts[] = [
                'icon' => 'fa-triangle-exclamation',
                'text' => "{$withoutProof} linha(s) com valor realizado e sem comprovante anexado.",
                'filter' => ['sem_comprovante' => '1'],
            ];
        }

        if ($missingInvoice > 0) {
            $alerts[] = [
                'icon' => 'fa-receipt',
                'text' => "{$missingInvoice} linha(s) com contrato OK aguardando nota fiscal.",
                'filter' => ['status' => FinanceCostStatus::ContratoFaltaNota->value],
            ];
        }

        if ($overpaid > 0) {
            $alerts[] = [
                'icon' => 'fa-money-bill-transfer',
                'text' => "{$overpaid} linha(s) com pagamento acima do valor realizado.",
                'filter' => ['pago_a_maior' => '1'],
            ];
        }

        if ($sheet->settlements()->exists() && abs($percentage - 100) > 0.01) {
            $alerts[] = [
                'icon' => 'fa-percent',
                'text' => 'A soma dos percentuais do acerto de sócios é '
                    .number_format($percentage, 2, ',', '.').'%, não 100%.',
                'filter' => [],
            ];
        }

        return $alerts;
    }

    /**
     * Totais por tipo de documento — alimenta o painel de controle documental do resumo.
     *
     * @return array<string,int>
     */
    public function documentCounts(FinanceSheet $sheet): array
    {
        return FinanceDocument::whereIn(
            'finance_cost_item_id',
            FinanceCostItem::where('finance_sheet_id', $sheet->id)->select('id')
        )
            ->selectRaw('kind, COUNT(*) total')
            ->groupBy('kind')
            ->pluck('total', 'kind')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Recalcula o ACERTO SÓCIOS a partir do resultado realizado, exceto nas linhas com valor
     * digitado à mão (`manual_amount`).
     *
     * @return array<int,array<string,mixed>>
     */
    public function settlements(FinanceSheet $sheet, float $actualResult): array
    {
        return $sheet->settlements()->orderBy('id')->get()->map(function ($s) use ($actualResult) {
            $amount = $s->manual_amount ? (float) $s->amount : $actualResult * ((float) $s->percentage) / 100;

            if (! $s->manual_amount && abs($amount - (float) $s->amount) > 0.005) {
                $s->update(['amount' => $amount]);
            }

            return [
                'id' => $s->id,
                'partner_name' => $s->partner_name,
                'finance_payment_source_id' => $s->finance_payment_source_id,
                'percentage' => (float) $s->percentage,
                'amount' => $amount,
                'manual_amount' => $s->manual_amount,
            ];
        })->all();
    }

    /**
     * Totais de várias planilhas de uma vez — a lista de eventos (specs/23 §8.1) precisa dos
     * números de N eventos e não pode chamar summary() num laço (N+1 x 3 consultas).
     *
     * @param  array<int>  $sheetIds
     * @return array<int,array<string,float>> indexado por finance_sheet_id
     */
    public function totalsForSheets(array $sheetIds): array
    {
        if (! $sheetIds) {
            return [];
        }

        $costs = FinanceCostItem::whereIn('finance_sheet_id', $sheetIds)
            ->selectRaw(sprintf(
                'finance_sheet_id, COALESCE(SUM(total_estimated_1),0) e1, COALESCE(SUM(%s),0) cur, '
                .'COALESCE(SUM(total_actual),0) act, COUNT(*) items',
                FinanceCostItem::currentEstimateSql(),
            ))
            ->groupBy('finance_sheet_id')
            ->get()->keyBy('finance_sheet_id');

        $revenues = FinanceRevenue::whereIn('finance_sheet_id', $sheetIds)
            ->selectRaw('finance_sheet_id, COALESCE(SUM(estimated_value),0) est, COALESCE(SUM(actual_value),0) act')
            ->groupBy('finance_sheet_id')
            ->get()->keyBy('finance_sheet_id');

        $paid = FinancePayment::join('finance_cost_items as i', 'i.id', '=', 'finance_payments.finance_cost_item_id')
            ->whereIn('i.finance_sheet_id', $sheetIds)
            ->selectRaw('i.finance_sheet_id as sheet_id, COALESCE(SUM(finance_payments.amount),0) total')
            ->groupBy('i.finance_sheet_id')
            ->pluck('total', 'sheet_id');

        $out = [];

        foreach ($sheetIds as $id) {
            $cost = $costs->get($id);
            $rev = $revenues->get($id);

            $costEstimated = (float) ($cost->cur ?? 0);
            $costActual = (float) ($cost->act ?? 0);
            $revEstimated = (float) ($rev->est ?? 0);
            $revActual = (float) ($rev->act ?? 0);

            $out[$id] = [
                'items' => (int) ($cost->items ?? 0),
                'revenue_estimated' => $revEstimated,
                'revenue_actual' => $revActual,
                'cost_estimated' => $costEstimated,
                'cost_actual' => $costActual,
                'result_estimated' => $revEstimated - $costEstimated,
                'result_actual' => $revActual - $costActual,
                'paid' => (float) ($paid[$id] ?? 0),
                'paid_pct' => $costActual > 0 ? round((float) ($paid[$id] ?? 0) / $costActual * 100, 1) : null,
            ];
        }

        return $out;
    }

    /** @return array{deviation:float,pct:float|null} */
    private function deviation(float $estimated, float $actual): array
    {
        return [
            'deviation' => $actual - $estimated,
            // Divisão por zero devolve null; a UI mostra "—", nunca "0%".
            'pct' => $estimated > 0 ? round($actual / $estimated * 100, 1) : null,
        ];
    }
}
