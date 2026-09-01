<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Linha do ACERTO SÓCIOS (specs/23 §4.7). */
class FinancePartnerSettlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'finance_sheet_id', 'finance_payment_source_id', 'partner_name',
        'percentage', 'amount', 'manual_amount',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'amount' => 'decimal:2',
        'manual_amount' => 'boolean',
    ];

    public function sheet(): BelongsTo
    {
        return $this->belongsTo(FinanceSheet::class, 'finance_sheet_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(FinancePaymentSource::class, 'finance_payment_source_id');
    }
}
