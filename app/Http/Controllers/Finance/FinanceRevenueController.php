<?php

namespace App\Http\Controllers\Finance;

use App\Domain\Enums\FinanceRevenueCategory;
use App\Http\Requests\Finance\StoreRevenueRequest;
use App\Models\Empresa;
use App\Models\Event;
use App\Models\FinancePaymentSource;
use App\Models\FinanceRevenue;
use App\Services\Finance\FinanceSheetProvider;
use App\Services\Finance\FinanceSummaryService;
use App\Support\FinancePresenter;

/** A aba RECEITAS (specs/23 §8.3). */
class FinanceRevenueController extends FinanceController
{
    public function __construct(
        private FinanceSheetProvider $sheets,
        private FinanceSummaryService $summary,
    ) {}

    public function index(Event $evento)
    {
        $sheet = $this->sheets->forEvent($evento);
        $this->authorize('view', $sheet);

        $revenues = $sheet->revenues()->with('empresa:id,corporate_name,trade_name')
            ->orderBy('position')->orderBy('id')->get();

        return view('financeiro.eventos.receitas', [
            'evento' => $evento,
            'sheet' => $sheet,
            'revenues' => $revenues,
            'totals' => $this->summary->revenueTotals($sheet),
            'categories' => FinanceRevenueCategory::options(),
            'empresas' => Empresa::orderBy('corporate_name')->get(['id', 'corporate_name', 'trade_name']),
            'sources' => FinancePaymentSource::active()->ordered()->get(['id', 'name']),
        ]);
    }

    public function store(StoreRevenueRequest $request, Event $evento)
    {
        $sheet = $this->sheets->forEvent($evento);
        $this->authorizeWrite($sheet);

        $revenue = $sheet->revenues()->create($request->validated() + [
            'position' => (int) $sheet->revenues()->max('position') + 1,
        ]);

        return response()->json(FinancePresenter::revenue($revenue->refresh()), 201);
    }

    public function update(StoreRevenueRequest $request, FinanceRevenue $revenue)
    {
        $this->authorizeWrite($revenue->loadMissing('sheet')->sheet);

        $revenue->update($request->validated());

        return response()->json(FinancePresenter::revenue($revenue->refresh()));
    }

    public function destroy(FinanceRevenue $revenue)
    {
        $this->authorizeWrite($revenue->loadMissing('sheet')->sheet);

        $revenue->delete();

        return response()->json(['ok' => true]);
    }
}
