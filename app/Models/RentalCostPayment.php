<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pagamento parcial de um custo do pedido de locação (specs/24 §4.6). */
class RentalCostPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'rental_order_cost_id', 'amount', 'paid_at', 'finance_payment_source_id', 'notes', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'date',
    ];

    public function cost(): BelongsTo
    {
        return $this->belongsTo(RentalOrderCost::class, 'rental_order_cost_id');
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
