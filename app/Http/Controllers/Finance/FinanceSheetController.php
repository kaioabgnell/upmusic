<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\CloseFinanceSheet;
use App\Domain\Enums\FinanceDocumentKind;
use App\Models\Event;
use App\Models\FinancePaymentSource;
use App\Models\FinanceSheet;
use App\Services\Finance\FinanceSheetProvider;
use App\Services\Finance\FinanceSummaryService;
use Illuminate\Http\Request;

/**
 * Lista de eventos e RESUMO GERAL do Financeiro do Evento (specs/23 §8.1 e §8.2).
 */
class FinanceSheetController extends FinanceController
{
    public function __construct(
        private FinanceSheetProvider $sheets,
        private FinanceSummaryService $summary,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', FinanceSheet::class);

        // Coordenador restrito por evento (specs/20): a lista já sai filtrada, não só o acesso.
        $allowed = $request->user()->allowedEventIds();

        $events = Event::query()
            ->with('financeSheet:id,event_id,status,uses_second_estimate')
            ->when($allowed !== null, fn ($q) => $q->whereIn('id', $allowed))
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($request->filled('status'), function ($q) use ($request) {
                return $request->status === 'sem_planilha'
                    ? $q->whereDoesntHave('financeSheet')
                    : $q->whereHas('financeSheet', fn ($q) => $q->where('status', $request->status));
            })
            ->orderByDesc('start_date')
            ->paginate(15)
            ->withQueryString();

        $totals = $this->summary->totalsForSheets(
            $events->pluck('financeSheet.id')->filter()->values()->all()
        );

        return view('financeiro.eventos.index', compact('events', 'totals'));
    }

    /** RESUMO GERAL do evento. A planilha nasce aqui se ainda não existir. */
    public function show(Event $evento)
    {
        $sheet = $this->sheets->forEvent($evento);
        $this->authorize('view', $sheet);

        $summary = $this->summary->summary($sheet);

        return view('financeiro.eventos.show', [
            'evento' => $evento,
            'sheet' => $sheet,
            'summary' => $summary,
            'byCategory' => $this->summary->byCategory($sheet),
            'bySource' => $this->summary->byPaymentSource($sheet),
            'documentCounts' => $this->summary->documentCounts($sheet),
            'documentKinds' => FinanceDocumentKind::cases(),
            'settlements' => $this->summary->settlements($sheet, $summary['result']['actual']),
            'alerts' => $this->summary->alerts($sheet),
            'sources' => FinancePaymentSource::active()->ordered()->get(['id', 'name', 'kind']),
        ]);
    }

    /** Liga/desliga o "Previsto 2" e guarda as observações da planilha. */
    public function updateConfig(Request $request, Event $evento)
    {
        $sheet = $this->sheets->forEvent($evento);
        $this->authorizeWrite($sheet);

        $data = $request->validate([
            'uses_second_estimate' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $sheet->update([
            'uses_second_estimate' => $request->boolean('uses_second_estimate'),
            'notes' => $data['notes'] ?? $sheet->notes,
        ]);

        return back()->with('success', 'Configuração da planilha atualizada.');
    }

    public function close(Event $evento, CloseFinanceSheet $action)
    {
        $sheet = $this->sheets->forEvent($evento);
        $this->authorize('close', $sheet);

        $action->close($sheet, request()->user());

        return back()->with('success', 'Prestação de contas fechada. A planilha ficou somente leitura.');
    }

    /** Reabrir é exclusivo do Admin (a policy nega para todo o resto; Gate::before libera o admin). */
    public function reopen(Event $evento, CloseFinanceSheet $action)
    {
        $sheet = $this->sheets->forEvent($evento);
        $this->authorize('reopen', $sheet);

        $action->reopen($sheet);

        return back()->with('success', 'Prestação de contas reaberta.');
    }
}
