<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Link público de "Solicitação de Nota" de um card — um por card, reutilizável. */
class CardInvoiceRequest extends Model
{
    protected $fillable = ['card_id', 'token', 'active', 'created_by'];

    protected $casts = ['active' => 'boolean'];

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(CardInvoiceSubmission::class)->latest();
    }
}
