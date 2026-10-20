<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Recebimento parcial da receita de um pedido de locação (specs/24 §4.4). */
class RentalReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'rental_order_id', 'amount', 'received_at', 'finance_payment_source_id', 'notes', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'received_at' => 'date',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(RentalOrder::class, 'rental_order_id');
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
