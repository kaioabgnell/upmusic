<?php

namespace App\Http\Controllers\Bid;

use App\Actions\Bid\EvaluateNotice;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bid\UpdateBidMatchRequest;
use App\Http\Requests\Bid\UpdateBidRequirementRequest;
use App\Models\BidNoticeRequirement;
use App\Models\BidRequirementMatch;

/**
 * Correção humana sobre o que a IA extraiu (ver specs/21 §9.6): editar/ignorar requisito e
 * sobrescrever uma conferência. Toda alteração recalcula o ranking na hora — sem custo de IA.
 */
class BidRequirementController extends Controller
{
    public function update(UpdateBidRequirementRequest $request, BidNoticeRequirement $requirement, EvaluateNotice $evaluate)
    {
        $data = $request->validated();

        // `expected` é reconstruído a partir dos campos do formulário, preservando o que a IA
        // extraiu e o operador não editou (ex.: porte, texto de origem).
        $expected = $requirement->expected ?? [];

        foreach ([
            'numeric_min' => 'expected_numeric_min',
            'percent_of_estimate' => 'expected_percent_of_estimate',
        ] as $key => $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            if ($data[$field] === null) {
                unset($expected[$key]);
            } else {
                $expected[$key] = (float) $data[$field];
            }
        }

        if (array_key_exists('expected_cnae', $data)) {
            $cnae = \App\Support\BidText::cnaeClass($data['expected_cnae']);
            if ($cnae) {
                $expected['cnae'] = $cnae;
                $expected['cnae_label'] = $data['expected_cnae'];
            } else {
                unset($expected['cnae'], $expected['cnae_label']);
            }
        }

        $requirement->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'kind' => $data['kind'],
            'bid_document_type_id' => $data['bid_document_type_id'] ?? null,
            'mandatory' => $data['mandatory'],
            'ignored' => $data['ignored'],
            'ignored_reason' => $data['ignored'] ? ($data['ignored_reason'] ?? null) : null,
            'expected' => $expected === [] ? null : $expected,
        ]);

        $evaluate->execute($requirement->notice);

        return back()->with('success', 'Requisito atualizado e aptidão recalculada.');
    }

    /** Override manual de uma célula da matriz. */
    public function updateMatch(UpdateBidMatchRequest $request, BidRequirementMatch $match, EvaluateNotice $evaluate)
    {
        $data = $request->validated();

        $match->update([
            'status' => $data['status'],
            'bid_document_id' => $data['bid_document_id'] ?? null,
            'reason' => $data['reason'] ?: 'Definido manualmente por '.auth()->user()->name.'.',
            'confidence' => 'alta',
            'manual_override' => true,
            'overridden_by' => auth()->id(),
        ]);

        $evaluate->execute($match->requirement->notice);

        return back()->with('success', 'Conferência atualizada e aptidão recalculada.');
    }

    /** Desfaz o override e devolve a célula ao motor automático. */
    public function resetMatch(BidRequirementMatch $match, EvaluateNotice $evaluate)
    {
        $this->authorize('update', $match);

        $match->update(['manual_override' => false, 'overridden_by' => null]);
        $evaluate->execute($match->requirement->notice);

        return back()->with('success', 'Conferência devolvida ao cálculo automático.');
    }
}
