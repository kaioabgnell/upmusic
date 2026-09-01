<?php

namespace App\Actions\Finance;

use App\Domain\Enums\FinanceArtStatus;
use App\Domain\Enums\FinanceCostStatus;
use App\Domain\Enums\FinanceDocumentKind;
use App\Models\FinanceCostItem;

/**
 * Deriva STATUS e ART a partir dos documentos presentes (specs/23 §6.4).
 *
 * O sistema sugere, o usuário decide: assim que alguém edita o status à mão, `status_auto` vira
 * false e esta Action não encosta mais no campo. `NaoAplicado` nunca é atribuído automaticamente —
 * é decisão humana.
 */
class DeriveCostItemStatus
{
    public function execute(FinanceCostItem $item): FinanceCostItem
    {
        $kinds = $item->documents()->pluck('kind')->map(
            fn ($k) => $k instanceof FinanceDocumentKind ? $k->value : $k
        )->unique();

        $updates = [];

        if ($item->status_auto && $item->status !== FinanceCostStatus::NaoAplicado) {
            $updates['status'] = match (true) {
                $kinds->contains(FinanceDocumentKind::Contrato->value)
                    && $kinds->contains(FinanceDocumentKind::NotaFiscal->value) => FinanceCostStatus::ContratoNotaOk,
                $kinds->contains(FinanceDocumentKind::Contrato->value) => FinanceCostStatus::ContratoFaltaNota,
                $kinds->contains(FinanceDocumentKind::Orcamento->value) => FinanceCostStatus::AguardandoContrato,
                default => FinanceCostStatus::Orcamento,
            };
        }

        // ART só avança: existe o documento -> OK. Sem documento o sistema não sabe distinguir
        // "não tem" de "aguardando envio", então mantém o que o usuário escolheu.
        if ($kinds->contains(FinanceDocumentKind::Art->value) && $item->art_status !== FinanceArtStatus::Ok) {
            $updates['art_status'] = FinanceArtStatus::Ok;
        }

        if ($updates) {
            $item->update($updates);
        }

        return $item;
    }
}
