<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cada minuta recebida pelo formulário do fornecedor (ver specs/19). O arquivo em si vira um
 * card_attachment (kind = minuta); aqui guardamos a observação, IP e data para auditoria.
 */
class CardSupplierSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'card_supplier_form_id', 'card_id', 'card_attachment_id', 'note', 'ip',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(CardSupplierForm::class, 'card_supplier_form_id');
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(CardAttachment::class, 'card_attachment_id');
    }
}
