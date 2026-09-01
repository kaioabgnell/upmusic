<?php

namespace App\Actions\Finance;

use App\Domain\Enums\FinanceDocumentKind;
use App\Models\CardAttachment;
use App\Models\FinanceCostItem;
use App\Models\FinanceDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

/**
 * Cria um documento de controle (specs/23 §4.5) garantindo a invariante da tabela:
 * ou o documento referencia um anexo do card, ou guarda um upload próprio — nunca os dois.
 */
class CreateFinanceDocument
{
    /**
     * Vincula um anexo já existente no card. Idempotente: o unique (item, anexo) faz o reenvio do
     * mesmo card não duplicar documento, então aqui devolvemos o que já existe.
     */
    public function fromAttachment(
        FinanceCostItem $item,
        CardAttachment $attachment,
        FinanceDocumentKind $kind,
        ?User $actor = null,
    ): FinanceDocument {
        $existing = $item->documents()->where('card_attachment_id', $attachment->id)->first();

        if ($existing) {
            // O tipo pode ter sido corrigido no card depois do primeiro envio.
            if ($existing->kind !== $kind) {
                $existing->update(['kind' => $kind]);
            }

            return $existing;
        }

        return $item->documents()->create([
            'kind' => $kind,
            'card_attachment_id' => $attachment->id,
            'uploaded_by' => $actor?->id ?? $attachment->uploaded_by,
        ]);
    }

    /** Upload feito direto no financeiro (guia, boleto, taxa que nunca teve card). */
    public function fromUpload(
        FinanceCostItem $item,
        UploadedFile $file,
        FinanceDocumentKind $kind,
        ?User $actor = null,
    ): FinanceDocument {
        $path = $file->store("finance-documents/{$item->id}", 'local');

        return $item->documents()->create([
            'kind' => $kind,
            'card_attachment_id' => null,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $actor?->id,
        ]);
    }

    /** Guarda de consistência usada nos testes e em qualquer criação manual. */
    public static function assertValid(FinanceDocument $document): void
    {
        $hasAttachment = $document->card_attachment_id !== null;
        $hasUpload = $document->path !== null;

        if ($hasAttachment === $hasUpload) {
            throw new InvalidArgumentException(
                'Documento do financeiro precisa referenciar um anexo do card OU ter arquivo próprio.'
            );
        }
    }
}
