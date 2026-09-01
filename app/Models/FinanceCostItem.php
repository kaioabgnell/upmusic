<?php

namespace App\Models;

use App\Domain\Enums\FinanceArtStatus;
use App\Domain\Enums\FinanceCostStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Linha da aba CUSTOS (specs/23 §4.3).
 *
 * `total_estimated_1`, `total_estimated_2` e `total_actual` são colunas GERADAS no banco: ficam
 * fora de $fillable e de qualquer INSERT/UPDATE (o MySQL/MariaDB recusa). Depois de salvar, use
 * `refresh()` para ler os totais recalculados.
 */
class FinanceCostItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'finance_sheet_id', 'fornecedor_categoria_id', 'description', 'status', 'status_auto',
        'art_status', 'fornecedor_id', 'supplier_name', 'authorized_by', 'authorized_by_name',
        'daily_count', 'quantity', 'unit_estimated_1', 'unit_estimated_2', 'unit_actual',
        'card_id', 'notes', 'position',
    ];

    protected $casts = [
        'status' => FinanceCostStatus::class,
        'status_auto' => 'boolean',
        'art_status' => FinanceArtStatus::class,
        'daily_count' => 'decimal:2',
        'quantity' => 'decimal:2',
        'unit_estimated_1' => 'decimal:2',
        'unit_estimated_2' => 'decimal:2',
        'unit_actual' => 'decimal:2',
        'total_estimated_1' => 'decimal:2',
        'total_estimated_2' => 'decimal:2',
        'total_actual' => 'decimal:2',
    ];

    public function sheet(): BelongsTo
    {
        return $this->belongsTo(FinanceSheet::class, 'finance_sheet_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(FornecedorCategoria::class, 'fornecedor_categoria_id');
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class);
    }

    public function authorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(FinanceDocument::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FinancePayment::class);
    }

    /**
     * Custo previsto vigente da linha (specs/23 §7): a linha já refinada vale pelo Previsto 2;
     * a que não foi refinada continua valendo pelo Previsto 1.
     */
    public function currentEstimate(): float
    {
        return $this->unit_estimated_2 === null
            ? (float) $this->total_estimated_1
            : (float) $this->total_estimated_2;
    }

    /** Expressão SQL equivalente a currentEstimate(), para os SUM() do resumo. */
    public static function currentEstimateSql(): string
    {
        return 'CASE WHEN unit_estimated_2 IS NULL THEN total_estimated_1 ELSE total_estimated_2 END';
    }

    public function supplierLabel(): ?string
    {
        return $this->fornecedor?->name ?: $this->supplier_name;
    }

    public function authorizerLabel(): ?string
    {
        return $this->authorizer?->name ?: $this->authorized_by_name;
    }
}
