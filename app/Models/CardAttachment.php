<?php

namespace App\Models;

use App\Domain\Enums\AttachmentKind;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CardAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'card_id', 'uploaded_by', 'kind', 'original_name', 'path', 'mime', 'size',
    ];

    protected $casts = [
        'kind' => AttachmentKind::class,
        'size' => 'integer',
    ];

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Preenchido só para anexos vindos do formulário de minuta do fornecedor (ver specs/19). */
    public function supplierSubmission(): HasOne
    {
        return $this->hasOne(CardSupplierSubmission::class, 'card_attachment_id');
    }
}
