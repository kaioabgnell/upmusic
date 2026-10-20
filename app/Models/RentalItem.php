<?php

namespace App\Models;

use App\Domain\Enums\RentalItemDeletionReason;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Item do inventário da Gestão de Locações (specs/24 §4.1) — LED, mesa, telão, som...
 *
 * `total_investment` é coluna GERADA (quantity * unit_cost) — fica fora do $fillable, como os totais
 * do Financeiro do Evento (specs/23).
 */
class RentalItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'sku', 'description', 'quantity', 'unit_cost', 'purchase_date',
        'rental_price', 'active', 'notes',
        // Preenchidos por App\Actions\Rentals\DeleteRentalItem, não por formulário (specs/24 §2 Q11-Q22).
        'deletion_reason', 'deletion_reason_other', 'deleted_by',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'total_investment' => 'decimal:2',
        'purchase_date' => 'date',
        'rental_price' => 'decimal:2',
        'active' => 'boolean',
        'deletion_reason' => RentalItemDeletionReason::class,
    ];

    public function orderItems(): HasMany
    {
        return $this->hasMany(RentalOrderItem::class);
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
