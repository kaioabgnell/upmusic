<?php

namespace App\Http\Controllers\Finance;

use App\Models\Event;
use App\Services\Finance\FinanceSheetProvider;
use App\Services\Finance\FinanceSummaryService;

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
    ) {}

    public function show(Event $evento)
    {
        $sheet = $this->sheets->forEvent($evento);
        $this->authorize('view', $sheet);

        return view('financeiro.eventos.relatorio', [
            'evento' => $evento,
            'sheet' => $sheet,
            'summary' => $this->summary->summary($sheet),
            'byCategory' => $this->summary->byCategory($sheet),
            'cashFlow' => $this->summary->cashFlow($sheet),
        ]);
    }
}
