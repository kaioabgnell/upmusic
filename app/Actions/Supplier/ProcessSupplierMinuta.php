<?php

namespace App\Actions\Supplier;

use App\Domain\Enums\AttachmentKind;
use App\Domain\Enums\MovementType;
use App\Models\Card;
use App\Models\CardSupplierForm;
use App\Models\CardSupplierSubmission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Recebe a minuta enviada pelo fornecedor via link público (ver specs/19): anexa o arquivo ao card
 * (kind = minuta, armazenamento privado), registra o submission para auditoria e avança o card
 * automaticamente para a próxima etapa do quadro. Tudo em transação. Diferente do formulário externo
 * (spec 11), NÃO cria card novo — anexa e movimenta um card já existente.
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

            $submission = $form->submissions()->create([
                'card_id' => $card->id,
                'card_attachment_id' => $attachment->id,
                'note' => $note,
                'ip' => $ip,
            ]);

            $this->advanceToNextColumn($card);

            return $submission;
        });
    }

    /**
     * Avança o card para a próxima coluna do quadro por posição — mesma noção de "próxima coluna"
     * já usada pela aprovação de etapa (specs/17, BoardColumn::nextColumn()). Sem usuário logado (o
     * envio é público, do fornecedor), então a movimentação fica sem `user_id`. Se o card já estiver
     * na última coluna, não há para onde avançar: fica como está, sem erro (é um efeito automático,
     * não uma ação com feedback de falha para o fornecedor).
     */
    private function advanceToNextColumn(Card $card): void
    {
        $nextColumn = $card->column->nextColumn();

        if (! $nextColumn) {
            return;
        }

        $fromColumnId = $card->board_column_id;

        $position = (int) $card->board->cards()
            ->where('board_column_id', $nextColumn->id)
            ->max('position') + 1;

        $card->update([
            'board_column_id' => $nextColumn->id,
            'position' => $position,
        ]);

        $card->movements()->create([
            'user_id' => null,
            'from_board_id' => $card->board_id,
            'to_board_id' => $card->board_id,
            'from_column_id' => $fromColumnId,
            'to_column_id' => $nextColumn->id,
            'type' => MovementType::MinutaRecebida->value,
        ]);
    }
}
