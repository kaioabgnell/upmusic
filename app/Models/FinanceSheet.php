<?php

namespace App\Models;

use App\Domain\Enums\FinanceSheetStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Planilha financeira de um evento (specs/23). Substitui o arquivo `FINANCEIRO - MODELO.xlsx`.
 */
class FinanceSheet extends Model
{
    use HasFactory;

    protected $fillable = ['event_id', 'uses_second_estimate', 'status', 'closed_at', 'closed_by', 'notes'];

    protected $casts = [
        'uses_second_estimate' => 'boolean',
        'status' => FinanceSheetStatus::class,
        'closed_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function costItems(): HasMany
    {
        return $this->hasMany(FinanceCostItem::class);
    }

    public function revenues(): HasMany
    {
        return $this->hasMany(FinanceRevenue::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(FinancePartnerSettlement::class);
    }

    /** Todos os pagamentos do evento, para os totais do resumo sem passar por cada linha. */
    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(FinancePayment::class, FinanceCostItem::class);
    }

    public function isClosed(): bool
    {
        return $this->status === FinanceSheetStatus::Fechado;
    }
}
