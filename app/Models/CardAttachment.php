<?php

namespace App\Models;

use App\Domain\Enums\AttachmentKind;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    /**
     * Onde este anexo é usado como documento de controle no Financeiro (specs/23). O arquivo não
     * é copiado: excluir o anexo aqui remove o documento de lá (cascade), e é por isso que
     * `CardController::destroyAttachment()` avisa/bloqueia antes de apagar.
     */
    public function financeDocuments(): HasMany
    {
        return $this->hasMany(FinanceDocument::class);
    }

    /** Preenchido só para anexos vindos do formulário de minuta do fornecedor (ver specs/19). */
    public function supplierSubmission(): HasOne
    {
        return $this->hasOne(CardSupplierSubmission::class, 'card_attachment_id');
    }
}
