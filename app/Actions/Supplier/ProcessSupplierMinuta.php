<?php

namespace App\Actions\Supplier;

use App\Domain\Enums\AttachmentKind;
use App\Models\CardSupplierForm;
use App\Models\CardSupplierSubmission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Recebe a minuta enviada pelo fornecedor via link público (ver specs/19): anexa o arquivo ao card
 * (kind = minuta, armazenamento privado) e registra o submission para auditoria. Tudo em transação.
 * Diferente do formulário externo (spec 11), NÃO cria card novo — anexa a um card já existente.
 */
class ProcessSupplierMinuta
{
    public function execute(CardSupplierForm $form, UploadedFile $file, ?string $note = null, ?string $ip = null): CardSupplierSubmission
    {
        return DB::transaction(function () use ($form, $file, $note, $ip) {
            $card = $form->card;

            $path = $file->store("supplier-minutas/{$card->id}", 'local');

            $attachment = $card->attachments()->create([
                'uploaded_by' => null,
                'kind' => AttachmentKind::Minuta->value,
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);

            return $form->submissions()->create([
                'card_id' => $card->id,
                'card_attachment_id' => $attachment->id,
                'note' => $note,
                'ip' => $ip,
            ]);
        });
    }
}
