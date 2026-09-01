<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\DeriveCostItemStatus;
use App\Domain\Enums\FinanceArtStatus;
use App\Domain\Enums\FinanceCostStatus;
use App\Http\Requests\Finance\StoreCostItemRequest;
use App\Http\Requests\Finance\UpdateCostItemRequest;
use App\Models\Event;
use App\Models\FinanceCostItem;
use App\Models\FinanceItemPreset;
use App\Models\FinancePaymentSource;
use App\Models\Fornecedor;
use App\Models\FornecedorCategoria;
use App\Models\User;
use App\Services\Finance\FinanceSheetProvider;
use App\Services\Finance\FinanceSummaryService;
use App\Support\FinancePresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * A aba CUSTOS (specs/23 §8.4) — a grade tipo planilha, com edição inline e autosave por linha.
 */
class FinanceCostItemController extends FinanceController
{
    public function __construct(
        private FinanceSheetProvider $sheets,
        private FinanceSummaryService $summary,
    ) {}

    public function index(Request $request, Event $evento)
    {
        $sheet = $this->sheets->forEvent($evento);
        $this->authorize('view', $sheet);

        $items = $this->filtered($request, $sheet->id)
            ->with(['categoria:id,nome', 'fornecedor:id,name', 'authorizer:id,name', 'documents', 'payments'])
            ->orderBy('position')->orderBy('id')
            ->paginate(100)
            ->withQueryString();

        // Totais do rodapé refletem o FILTRO aplicado, não a planilha inteira — é o que a pessoa
        // está olhando na tela.
        $footer = $this->filtered($request, $sheet->id)
            ->selectRaw(sprintf(
                'COALESCE(SUM(total_estimated_1),0) e1, COALESCE(SUM(total_estimated_2),0) e2, '
                .'COALESCE(SUM(%s),0) cur, COALESCE(SUM(total_actual),0) act, COUNT(*) total',
                FinanceCostItem::currentEstimateSql(),
            ))->first();

        return view('financeiro.eventos.custos', [
            'evento' => $evento,
            'sheet' => $sheet,
            'items' => $items,
            'rows' => $items->getCollection()->map(fn ($i) => FinancePresenter::costItem($i))->values(),
            'footer' => $footer,
            'paidTotal' => $this->paidTotalFor($request, $sheet->id),
            'categorias' => FornecedorCategoria::active()->orderBy('nome')->get(['id', 'nome']),
            'fornecedores' => Fornecedor::active()->orderBy('name')->get(['id', 'name']),
            'usuarios' => User::where('active', true)->orderBy('name')->get(['id', 'name']),
            'sources' => FinancePaymentSource::active()->ordered()->get(['id', 'name', 'kind']),
            'statuses' => FinanceCostStatus::options(),
            'artStatuses' => FinanceArtStatus::options(),
            'presets' => FinanceItemPreset::active()
                ->orderBy('description')
                ->get(['fornecedor_categoria_id', 'description'])
                ->groupBy('fornecedor_categoria_id')
                ->map(fn ($g) => $g->pluck('description')->values()),
        ]);
    }

    public function store(StoreCostItemRequest $request, Event $evento)
    {
        $sheet = $this->sheets->forEvent($evento);
        $this->authorizeWrite($sheet);

        $item = $sheet->costItems()->create($request->validated() + [
            'position' => (int) $sheet->costItems()->max('position') + 1,
        ]);

        return response()->json(FinancePresenter::costItem($item->refresh()), 201);
    }

    public function update(UpdateCostItemRequest $request, FinanceCostItem $item, DeriveCostItemStatus $derive)
    {
        // loadMissing explícito: Model::preventLazyLoading() está ligado fora de produção e o
        // model veio do route binding, sem eager load.
        $this->authorizeWrite($item->loadMissing('sheet')->sheet);

        $data = $request->validated();

        // Status escolhido à mão desliga a derivação automática (specs/23 §6.4): a partir daqui o
        // campo é do usuário, e chegar um contrato novo não muda mais o que ele decidiu.
        if (array_key_exists('status', $data) && $data['status'] !== $item->status->value) {
            $data['status_auto'] = false;
        }

        $item->update($data);

        if ($item->status_auto) {
            $derive->execute($item);
        }

        return response()->json(FinancePresenter::costItem($item->refresh()));
    }

    public function destroy(FinanceCostItem $item)
    {
        $this->authorizeWrite($item->loadMissing('sheet')->sheet);

        $item->delete();

        return response()->json(['ok' => true]);
    }

    /** Itens como INFLUENCER se repetem N vezes na planilha — duplicar poupa redigitação. */
    public function duplicate(FinanceCostItem $item)
    {
        $this->authorizeWrite($item->loadMissing('sheet')->sheet);

        $copy = $item->replicate([
            // A cópia é uma linha nova do financeiro: não herda o vínculo com o card nem os
            // documentos, senão o mesmo comprovante provaria duas despesas.
            'card_id',
            // Colunas geradas: o banco recusa um INSERT que as mencione.
            'total_estimated_1', 'total_estimated_2', 'total_actual',
        ]);
        $copy->card_id = null;
        $copy->position = (int) $item->sheet->costItems()->max('position') + 1;
        $copy->save();

        return response()->json(FinancePresenter::costItem($copy->refresh()), 201);
    }

    /** Ações em massa da grade: status, ART e exclusão. */
    public function bulk(Request $request, Event $evento)
    {
        $sheet = $this->sheets->forEvent($evento);
        $this->authorizeWrite($sheet);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'action' => ['required', 'in:status,art_status,delete'],
            'value' => ['nullable', 'string'],
        ]);

        $query = FinanceCostItem::where('finance_sheet_id', $sheet->id)->whereIn('id', $data['ids']);

        $affected = match ($data['action']) {
            'delete' => $query->delete(),
            'status' => $query->update([
                'status' => FinanceCostStatus::from($data['value'])->value,
                'status_auto' => false,
            ]),
            'art_status' => $query->update(['art_status' => FinanceArtStatus::from($data['value'])->value]),
        };

        return response()->json(['ok' => true, 'affected' => $affected]);
    }

    // ------------------------------------------------------------------

    /** Filtros server-side da grade (specs/23 §8.4). */
    private function filtered(Request $request, int $sheetId)
    {
        return FinanceCostItem::query()
            ->where('finance_sheet_id', $sheetId)
            ->when($request->categoria_id, fn ($q, $v) => $q->where('fornecedor_categoria_id', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->art_status, fn ($q, $v) => $q->where('art_status', $v))
            ->when($request->fornecedor_id, fn ($q, $v) => $q->where('fornecedor_id', $v))
            ->when($request->search, fn ($q, $v) => $q->where('description', 'like', "%{$v}%"))
            ->when($request->boolean('sem_comprovante'), fn ($q) => $q
                ->whereNotNull('unit_actual')
                ->whereDoesntHave('documents', fn ($d) => $d->where('kind', 'comprovante')))
            ->when($request->boolean('falta_pagar'), fn ($q) => $q
                ->whereRaw('total_actual > (select COALESCE(SUM(amount),0) from finance_payments
                            where finance_payments.finance_cost_item_id = finance_cost_items.id)'))
            ->when($request->boolean('pago_a_maior'), fn ($q) => $q
                ->whereRaw('total_actual < (select COALESCE(SUM(amount),0) from finance_payments
                            where finance_payments.finance_cost_item_id = finance_cost_items.id)'))
            ->when($request->source_id, fn ($q, $v) => $q
                ->whereHas('payments', fn ($p) => $p->where('finance_payment_source_id', $v)));
    }

    /** "PAGO" do rodapé, também respeitando o filtro. */
    private function paidTotalFor(Request $request, int $sheetId): float
    {
        return (float) DB::table('finance_payments')
            ->whereIn('finance_cost_item_id', $this->filtered($request, $sheetId)->select('id'))
            ->sum('amount');
    }
}
