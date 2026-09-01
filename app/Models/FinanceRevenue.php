<?php

namespace App\Models;

use App\Domain\Enums\FinanceRevenueCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Linha da aba RECEITAS (specs/23 §4.6). `pending_value` (FALTA RECEBER) é coluna gerada —
 * fora de $fillable.
 */
class FinanceRevenue extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'finance_sheet_id', 'category', 'description', 'empresa_id', 'estimated_value',
        'actual_value', 'received_value', 'finance_payment_source_id', 'received_at', 'notes', 'position',
    ];

    protected $casts = [
        'category' => FinanceRevenueCategory::class,
        'estimated_value' => 'decimal:2',
        'actual_value' => 'decimal:2',
        'received_value' => 'decimal:2',
        'pending_value' => 'decimal:2',
        'received_at' => 'date',
    ];

    public function sheet(): BelongsTo
    {
        return $this->belongsTo(FinanceSheet::class, 'finance_sheet_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(FinancePaymentSource::class, 'finance_payment_source_id');
    }
}
