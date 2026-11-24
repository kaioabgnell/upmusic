<?php

namespace App\Http\Controllers\Finance;

use App\Models\Event;
use App\Services\Finance\CostFilterOptions;
use App\Services\Finance\CostItemFilter;
use App\Services\Finance\FinanceSheetProvider;
use App\Services\Finance\FinanceSummaryService;
use Illuminate\Http\Request;

/**
 * Aba RELATÓRIO do evento: os mesmos números do Resumo, só que em gráficos (specs/23 §8.2 —
 * "gráficos em Chart.js ou SVG inline, paleta do design system").
 *
 * Tudo aqui é do evento aberto e só dele: as agregações recebem a planilha do evento, nunca uma
 * consulta global. Nenhum número novo é inventado — os dados vêm das mesmas agregações que
 * alimentam o Resumo, para as duas telas nunca discordarem.
 */
class FinanceReportController extends FinanceController
{
    public function __construct(
        private FinanceSheetProvider $sheets,
        private FinanceSummaryService $summary,
        private CostFilterOptions $filterOptions,
    ) {}

    /**
     * Os mesmos filtros da aba CUSTOS (CostItemFilter): os gráficos passam a mostrar só as linhas
     * filtradas, e o "Exportar" daqui leva o mesmo recorte para o arquivo.
     */
    public function show(Request $request, Event $evento)
    {
        $sheet = $this->sheets->forEvent($evento);
        $this->authorize('view', $sheet);

        $filter = CostItemFilter::fromRequest($request);
        $active = $filter->isEmpty() ? null : $filter;

        return view('financeiro.eventos.relatorio', $this->filterOptions->for($sheet) + [
            'evento' => $evento,
            'sheet' => $sheet,
            'filter' => $active,
            'summary' => $this->summary->summary($sheet, $active),
            'byCategory' => $this->summary->byCategory($sheet, $active),
            'cashFlow' => $this->summary->cashFlow($sheet, $active),
        ]);
    }
}
