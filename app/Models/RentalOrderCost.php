<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Custo simples do pedido de locação (specs/24 §4.5) — na prática, sublocação de itens que faltam
 * no inventário próprio (specs/24 §2 Q27). Vínculo com uma linha de item é opcional.
 */
class RentalOrderCost extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'rental_order_id', 'rental_order_item_id', 'description', 'estimated_value',
        'actual_value', 'due_date', 'notes', 'position',
    ];

    protected $casts = [
        'estimated_value' => 'decimal:2',
        'actual_value' => 'decimal:2',
        'due_date' => 'date',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(RentalOrder::class, 'rental_order_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(RentalOrderItem::class, 'rental_order_item_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(RentalCostPayment::class)->orderByDesc('paid_at')->orderByDesc('id');
    }
}
