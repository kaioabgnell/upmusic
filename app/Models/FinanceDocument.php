<?php

namespace App\Models;

use App\Domain\Enums\FinanceDocumentKind;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Documento de controle de uma linha de custo (specs/23 §4.5).
 *
 * Ou aponta para um anexo do card (`card_attachment_id`, sem cópia de arquivo) ou guarda um upload
 * feito direto no financeiro (`path`) — nunca os dois. Os acessores abaixo escondem essa diferença
 * de quem só quer exibir o arquivo.
 */
class FinanceDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'finance_cost_item_id', 'kind', 'card_attachment_id',
        'original_name', 'path', 'mime', 'size', 'uploaded_by',
    ];

    protected $casts = [
        'kind' => FinanceDocumentKind::class,
        'size' => 'integer',
    ];

    public function costItem(): BelongsTo
    {
        return $this->belongsTo(FinanceCostItem::class, 'finance_cost_item_id');
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(CardAttachment::class, 'card_attachment_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function fromCard(): bool
    {
        return $this->card_attachment_id !== null;
    }

    /** Caminho real no disco `local`, venha do card ou do upload direto. */
    public function storagePath(): ?string
    {
        return $this->fromCard() ? $this->attachment?->path : $this->path;
    }

    public function displayName(): string
    {
        return $this->fromCard()
            ? ($this->attachment?->original_name ?? 'Arquivo')
            : ($this->original_name ?? 'Arquivo');
    }

    public function displaySize(): int
    {
        return $this->fromCard() ? (int) ($this->attachment?->size ?? 0) : (int) $this->size;
    }
}
