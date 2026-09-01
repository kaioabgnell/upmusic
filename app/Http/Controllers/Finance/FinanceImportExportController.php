<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\ImportFinanceSpreadsheet;
use App\Models\Event;
use App\Services\Finance\FinanceExportService;
use App\Services\Finance\FinanceSheetProvider;
use Illuminate\Http\Request;

/**
 * Export no layout do arquivo modelo e import da planilha preenchida (specs/23 §12).
 */
class FinanceImportExportController extends FinanceController
{
    public function __construct(private FinanceSheetProvider $sheets) {}

    public function export(Event $evento, FinanceExportService $service)
    {
        $sheet = $this->sheets->forEvent($evento);
        $this->authorize('view', $sheet);

        $path = $service->toXlsx($sheet);

        return response()->download($path, $service->filename($sheet))->deleteFileAfterSend();
    }

    /** Pré-visualização obrigatória: nada é gravado antes de o usuário conferir. */
    public function importPreview(Request $request, Event $evento, ImportFinanceSpreadsheet $action)
    {
        $sheet = $this->sheets->forEvent($evento);
        $this->authorizeWrite($sheet);

        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240']]);

        $preview = $action->preview($request->file('file'));

        return view('financeiro.eventos.importar', [
            'evento' => $evento,
            'sheet' => $sheet,
            'costs' => $preview['costs'],
            'revenues' => $preview['revenues'],
            'warnings' => $preview['warnings'],
        ]);
    }

    public function import(Request $request, Event $evento, ImportFinanceSpreadsheet $action)
    {
        $sheet = $this->sheets->forEvent($evento);
        $this->authorizeWrite($sheet);

        $data = $request->validate([
            'costs' => ['nullable', 'array'],
            'revenues' => ['nullable', 'array'],
        ]);

        $counts = $action->import(
            $sheet,
            $this->decodeRows($data['costs'] ?? []),
            $this->decodeRows($data['revenues'] ?? []),
            $request->user(),
        );

        return redirect()
            ->route('finance.costs.index', $evento)
            ->with('success', "{$counts['costs']} linha(s) de custo, {$counts['revenues']} receita(s) e "
                ."{$counts['payments']} pagamento(s) importados.");
    }

    /**
     * A pré-visualização devolve cada linha como JSON num input hidden (é o shape que a Action
     * produziu, e remontá-lo campo a campo no formulário só criaria espaço para divergência).
     */
    private function decodeRows(array $rows): array
    {
        return collect($rows)
            ->map(fn ($row) => is_array($row) ? $row : json_decode((string) $row, true))
            ->filter(fn ($row) => is_array($row))
            ->values()
            ->all();
    }
}
