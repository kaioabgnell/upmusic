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
        'finance_sheet_id', 'category', 'description', 'cost_center_id', 'empresa_id', 'estimated_value',
        'actual_value', 'received_value', 'finance_payment_source_id', 'bank_account_id', 'received_at', 'notes', 'position',
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

    /** withTrashed: o centro excluído continua identificando a linha antiga (specs/25). */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class)->withTrashed();
    }

    /** Para onde entrou o dinheiro (specs/26). withTrashed: a conta excluída continua identificando a receita. */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class)->withTrashed();
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(FinancePaymentSource::class, 'finance_payment_source_id');
    }
}
