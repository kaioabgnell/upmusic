<?php

namespace App\Models;

use App\Domain\Enums\RentalOrderLogType;
use App\Domain\Enums\RentalOrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Histórico do pedido de locação (specs/24 §4.7). */
class RentalOrderLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'rental_order_id', 'user_id', 'type', 'from_status', 'to_status', 'message', 'payload', 'created_at',
    ];

    protected $casts = [
        'type' => RentalOrderLogType::class,
        'from_status' => RentalOrderStatus::class,
        'to_status' => RentalOrderStatus::class,
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(RentalOrder::class, 'rental_order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
