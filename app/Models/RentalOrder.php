<?php

namespace App\Models;

use App\Domain\Enums\RentalDiscountType;
use App\Domain\Enums\RentalOrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pedido de Locação (specs/24 §4.2) — o "card" do Kanban próprio do módulo. Isolado de `cards`,
 * `boards` e `events`.
 *
 * `subtotal` e `revenue_estimated` são desnormalizados, mantidos só por
 * `App\Actions\Rentals\RecalculateRentalOrderTotals`.
 */
class RentalOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'status', 'position', 'empresa_id', 'client_name', 'location',
        'start_at', 'end_at', 'subtotal', 'discount_type', 'discount_value',
        'revenue_estimated', 'revenue_actual', 'revenue_due_date',
        'has_availability_warning', 'has_return_divergence', 'notes',
        'closed_at', 'closed_by', 'cancelled_at', 'cancel_reason', 'created_by',
    ];

    protected $casts = [
        'status' => RentalOrderStatus::class,
        'position' => 'integer',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount_type' => RentalDiscountType::class,
        'discount_value' => 'decimal:2',
        'revenue_estimated' => 'decimal:2',
        'revenue_actual' => 'decimal:2',
        'revenue_due_date' => 'date',
        'has_availability_warning' => 'boolean',
        'has_return_divergence' => 'boolean',
        'closed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RentalOrderItem::class)->orderBy('position')->orderBy('id');
    }

    public function costs(): HasMany
    {
        return $this->hasMany(RentalOrderCost::class)->orderBy('position')->orderBy('id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(RentalReceipt::class)->orderByDesc('received_at')->orderByDesc('id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(RentalOrderLog::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function clientLabel(): ?string
    {
        return $this->empresa?->trade_name ?: $this->empresa?->corporate_name ?: $this->client_name;
    }

    public function isLocked(): bool
    {
        return $this->status->isLocked();
    }
}
