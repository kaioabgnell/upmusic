<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pagamento de uma linha de custo (specs/23 §4.4). Pode ser parcial e de fontes diferentes. */
class FinancePayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'finance_cost_item_id', 'finance_payment_source_id', 'amount', 'paid_at', 'notes', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'date',
    ];

    public function costItem(): BelongsTo
    {
        return $this->belongsTo(FinanceCostItem::class, 'finance_cost_item_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(FinancePaymentSource::class, 'finance_payment_source_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
