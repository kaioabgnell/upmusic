<?php

namespace App\Actions\Captures;

use App\Actions\Cards\CreateCard;
use App\Domain\Enums\CaptureStatus;
use App\Domain\Enums\CardOrigin;
use App\Models\Board;
use App\Models\Card;
use App\Models\CardCapture;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessQuickCapture
{
    public function __construct(private CreateCard $createCard) {}

    /**
     * Transforma uma captura pendente em card, movendo o anexo do staging para o card. $data aceita
     * board_id (obrigatório) e, opcionalmente, title/empresa_id/fornecedor_id/event_id/estimated_value.
     */
    public function execute(CardCapture $capture, array $data, User $actor): Card
    {
        return DB::transaction(function () use ($capture, $data, $actor) {
            $board = Board::findOrFail($data['board_id']);

            $title = $data['title']
                ?? $capture->suggested_title
                ?? pathinfo($capture->original_name, PATHINFO_FILENAME)
                ?? ('Captura — '.now()->format('d/m/Y'));

            $card = $this->createCard->execute($board, [
                'title' => Str::limit($title, 180, ''),
                'empresa_id' => $data['empresa_id'] ?? null,
                'fornecedor_id' => $data['fornecedor_id'] ?? null,
                'event_id' => $data['event_id'] ?? null,
                'estimated_value' => $data['estimated_value'] ?? null,
            ], $actor, CardOrigin::CapturaRapida);

            $newPath = "card-attachments/{$card->id}/".basename($capture->path);
            Storage::disk('local')->move($capture->path, $newPath);

            $card->attachments()->create([
                'uploaded_by' => $actor->id,
                'kind' => $data['kind'],
                'original_name' => $capture->original_name,
                'path' => $newPath,
                'mime' => $capture->mime,
                'size' => $capture->size,
            ]);

            $capture->update([
                'status' => CaptureStatus::Processado,
                'card_id' => $card->id,
                'board_id' => $board->id,
            ]);

            return $card;
        });
    }
}
