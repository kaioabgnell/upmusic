<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\SyncCardToFinance;
use App\Domain\Enums\CardNegociado;
use App\Domain\Enums\FinanceDocumentKind;
use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Event;
use App\Models\FinanceCostItem;
use App\Models\FinanceItemPreset;
use App\Models\FornecedorCategoria;
use App\Support\Br;
use Illuminate\Http\Request;

/**
 * A ponte no lado do Kanban (specs/23 §6): "Enviar para o Financeiro" no painel do card.
 *
 * Autorização pela CardPolicy — quem edita o card pode empurrar a despesa para o financeiro,
 * mesmo sem acesso ao módulo. O efeito é criar/atualizar uma linha, não ler o financeiro do evento.
 */
class CardFinanceController extends Controller
{
    /** Dados do modal de confirmação: sugestões + anexos com o tipo de cada um. */
    public function preview(Card $card)
    {
        $this->authorize('update', $card);

        $card->load(['event:id,name', 'fornecedor:id,name,fornecedor_categoria_id', 'attachments']);

        $existing = FinanceCostItem::where('card_id', $card->id)->first();

        return response()->json([
            'card' => [
                'id' => $card->id,
                'title' => $card->title,
                'event_id' => $card->event_id,
                'event_name' => $card->event?->name,
                'fornecedor_id' => $card->fornecedor_id,
                'fornecedor_categoria_id' => $card->fornecedor?->fornecedor_categoria_id,
                'estimated_value' => (float) ($card->estimated_value ?? 0),
                'actual_value' => $this->suggestedActual($card),
            ],
            'existing_item' => $existing ? [
                'id' => $existing->id,
                'description' => $existing->description,
                'documents_count' => $existing->documents()->count(),
            ] : null,
            'attachments' => $card->attachments->map(function ($a) {
                $suggested = FinanceDocumentKind::fromAttachmentKind($a->kind);

                return [
                    'id' => $a->id,
                    'name' => $a->original_name,
                    'attachment_kind' => $a->kind->value,
                    'attachment_label' => $a->kind->label(),
                    'suggested_kind' => $suggested?->value,
                    // `geral` e `minuta` chegam desmarcados: exigem classificação manual.
                    'checked' => $suggested !== null,
                ];
            })->values(),
            'kinds' => collect(FinanceDocumentKind::cases())->map(fn ($k) => [
                'value' => $k->value, 'label' => $k->label(),
            ])->values(),
            'categorias' => FornecedorCategoria::active()->orderBy('nome')->get(['id', 'nome']),
            'presets' => FinanceItemPreset::active()->orderBy('description')
                ->get(['fornecedor_categoria_id', 'description'])
                ->groupBy('fornecedor_categoria_id')
                ->map(fn ($g) => $g->pluck('description')->values()),
            'events' => Event::active()->orderByDesc('start_date')->get(['id', 'name']),
        ]);
    }

    public function sync(Request $request, Card $card, SyncCardToFinance $action)
    {
        $this->authorize('update', $card);

        $data = $request->validate([
            'event_id' => ['nullable', 'exists:events,id'],
            'fornecedor_categoria_id' => ['nullable', 'exists:fornecedor_categorias,id'],
            'description' => ['nullable', 'string', 'max:180'],
            'unit_estimated_1' => ['nullable'],
            'unit_actual' => ['nullable'],
            'attachment_ids' => ['nullable', 'array'],
            'attachment_ids.*' => ['integer'],
            'kinds' => ['nullable', 'array'],
        ]);

        // O modal permite vincular o evento na hora — sem evento não existe planilha para receber
        // a despesa, e a Action recusaria o envio.
        if (! empty($data['event_id']) && $card->event_id !== (int) $data['event_id']) {
            $card->update(['event_id' => $data['event_id']]);
        }

        $item = $action->execute(
            $card->refresh(),
            $request->user(),
            array_filter([
                'fornecedor_categoria_id' => $data['fornecedor_categoria_id'] ?? null,
                'description' => $data['description'] ?? null,
                'unit_estimated_1' => Br::money($data['unit_estimated_1'] ?? null),
                'unit_actual' => Br::money($data['unit_actual'] ?? null),
            ], fn ($v) => $v !== null),
            $data['attachment_ids'] ?? null,
            $data['kinds'] ?? [],
        );

        return response()->json([
            'ok' => true,
            'item_id' => $item->id,
            'event_id' => $item->loadMissing('sheet')->sheet->event_id,
            'url' => route('finance.costs.index', ['evento' => $item->sheet->event_id]).'#linha-'.$item->id,
            'message' => 'Card enviado ao Financeiro do evento.',
        ], 201);
    }

    /** Mesma regra do §6.5: o valor negociado tem precedência sobre `actual_value`. */
    private function suggestedActual(Card $card): ?float
    {
        $value = match ($card->negociado) {
            CardNegociado::ComNota => $card->valor_com_nota,
            CardNegociado::SemNota => $card->valor_sem_nota,
            default => $card->actual_value,
        };

        return $value === null ? null : (float) $value;
    }
}
