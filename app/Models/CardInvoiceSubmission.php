<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Um envio do link de Solicitação de Nota: os dados digitados e a NF anexada ao card. */
class CardInvoiceSubmission extends Model
{
    protected $fillable = [
        'card_invoice_request_id', 'card_id', 'card_attachment_id', 'cnpj', 'name', 'requester_name',
        'value', 'service_date', 'service_description', 'payment_data', 'ip',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'service_date' => 'date',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(CardInvoiceRequest::class, 'card_invoice_request_id');
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
