<?php

namespace App\Observers;

use App\Actions\Finance\CreateFinanceDocument;
use App\Actions\Finance\DeriveCostItemStatus;
use App\Domain\Enums\FinanceDocumentKind;
use App\Models\CardAttachment;
use App\Models\FinanceCostItem;

/**
 * Sincronia contínua card -> financeiro (specs/23 §6.1, gatilho 3).
 *
 * Anexo novo num card JÁ vinculado a uma linha de custo vira documento de controle na hora. Sem
 * isso, o comprovante que chega depois do envio inicial ficaria só no card — e a planilha voltaria
 * a divergir, que é exatamente o problema que este módulo resolve.
 */
class CardAttachmentObserver
{
    public function __construct(
        private CreateFinanceDocument $documents,
        private DeriveCostItemStatus $derive,
    ) {}

    public function created(CardAttachment $attachment): void
    {
        // `geral` e `minuta` não têm mapa automático (specs/23 §6.4): esperam classificação manual.
        $kind = FinanceDocumentKind::fromAttachmentKind($attachment->kind);

        if (! $kind) {
            return;
        }

        $item = FinanceCostItem::with('sheet')->where('card_id', $attachment->card_id)->first();

        if (! $item || $item->sheet->isClosed()) {
            return;
        }

        $this->documents->fromAttachment($item, $attachment, $kind);
        $this->derive->execute($item);
    }
}
