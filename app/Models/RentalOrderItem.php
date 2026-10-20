<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Linha de item do pedido de locação (specs/24 §4.3). `item_name` e `unit_price` são snapshots
 * gravados na criação — mudar o cadastro do item depois não altera pedidos já lançados.
 *
 * `total` é coluna GERADA (quantity * daily_count * unit_price) — fora do $fillable.
 */
class RentalOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'rental_order_id', 'rental_item_id', 'item_name', 'quantity', 'daily_count',
        'unit_price', 'returned_quantity', 'position',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'daily_count' => 'integer',
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
        'returned_quantity' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(RentalOrder::class, 'rental_order_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(RentalItem::class, 'rental_item_id');
    }

    public function costs(): HasMany
    {
        return $this->hasMany(RentalOrderCost::class);
    }
}
