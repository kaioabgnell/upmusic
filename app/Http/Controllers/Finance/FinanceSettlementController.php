<?php

namespace App\Http\Controllers\Finance;

use App\Models\Event;
use App\Services\Finance\FinanceSheetProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** ACERTO SÓCIOS do resumo (specs/23 §4.7) — salvo em bloco, como a tabelinha da planilha. */
class FinanceSettlementController extends FinanceController
{
    public function __construct(private FinanceSheetProvider $sheets) {}

    public function sync(Request $request, Event $evento)
    {
        $sheet = $this->sheets->forEvent($evento);
        $this->authorizeWrite($sheet);

        $data = $request->validate([
            'partners' => ['present', 'array'],
            'partners.*.id' => ['nullable', 'integer'],
            'partners.*.partner_name' => ['required', 'string', 'max:120'],
            'partners.*.finance_payment_source_id' => ['nullable', 'exists:finance_payment_sources,id'],
            'partners.*.percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'partners.*.manual_amount' => ['nullable', 'boolean'],
            'partners.*.amount' => ['nullable', 'numeric'],
        ]);

        DB::transaction(function () use ($sheet, $data) {
            $keep = [];

            foreach ($data['partners'] as $row) {
                $settlement = $sheet->settlements()->updateOrCreate(
                    ['id' => $row['id'] ?? null],
                    [
                        'partner_name' => $row['partner_name'],
                        'finance_payment_source_id' => $row['finance_payment_source_id'] ?? null,
                        'percentage' => $row['percentage'],
                        'manual_amount' => (bool) ($row['manual_amount'] ?? false),
                        'amount' => $row['amount'] ?? 0,
                    ],
                );
                $keep[] = $settlement->id;
            }

            // Linha removida na tela some do banco — a tabela é o espelho do que o usuário salvou.
            $sheet->settlements()->whereNotIn('id', $keep ?: [0])->delete();
        });

        return back()->with('success', 'Acerto de sócios atualizado.');
    }
}
