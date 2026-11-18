<?php

namespace App\Models;

use App\Domain\Enums\FixedCostGenerationMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * Custo fixo recorrente da empresa (specs/27): aluguel, água, parcelamento, empréstimo. Cada parcela
 * vira um card no quadro de custos fixos de destino. As parcelas já geradas ficam em
 * `fixed_cost_installments`; as que faltam são calculadas daqui.
 */
class FixedCost extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'empresa_id', 'board_id', 'board_column_id', 'name', 'description', 'installment_value',
        'installments_total', 'start_installment', 'first_due_date', 'generation_mode', 'lead_days',
        'active', 'created_by',
    ];

    protected $casts = [
        'installment_value' => 'decimal:2',
        'installments_total' => 'integer',
        'start_installment' => 'integer',
        'first_due_date' => 'immutable_date',
        'generation_mode' => FixedCostGenerationMode::class,
        'lead_days' => 'integer',
        'active' => 'boolean',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class)->withTrashed();
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class)->withTrashed();
    }

    public function column(): BelongsTo
    {
        return $this->belongsTo(BoardColumn::class, 'board_column_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(FixedCostInstallment::class)->orderBy('number');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * Vencimento do `$offset`-ésimo mês do cronograma: mesmo dia do primeiro vencimento, `$offset` meses
     * depois. Mês sem esse dia cai no último dia do mês. A conta parte SEMPRE do primeiro vencimento —
     * nunca da parcela anterior —, senão um 31/01 viraria 28/02 e depois 28/03 para sempre.
     */
    public function dueDateForOffset(int $offset): CarbonImmutable
    {
        return CarbonImmutable::parse($this->first_due_date->toDateString())->addMonthsNoOverflow($offset);
    }

    /** Quantos meses depois do primeiro vencimento cai `$date` (pelo ano/mês, ignorando o dia). */
    public function offsetOf(CarbonImmutable $date): int
    {
        return ($date->year - $this->first_due_date->year) * 12 + ($date->month - $this->first_due_date->month);
    }

    /**
     * Vencimento da parcela `$number`. Toda parcela tem linha no cronograma (specs/27 §2.3) e o
     * vencimento vem dela — depois de uma exclusão, o número deixa de dizer o mês. A fórmula só vale
     * como reserva para uma parcela que ainda não tem linha.
     */
    public function dueDateFor(int $number): CarbonImmutable
    {
        $installment = $this->installmentsList()->firstWhere('number', $number);

        return $installment?->due_date ?? $this->dueDateForOffset($number - $this->start_installment);
    }

    /** Dia em que a parcela entra na janela de geração (modo "X dias antes"); null no modo "todas agora". */
    public function generationDateFor(int $number): ?CarbonImmutable
    {
        if ($this->generation_mode !== FixedCostGenerationMode::LeadDays) {
            return null;
        }

        return $this->dueDateFor($number)->subDays((int) $this->lead_days);
    }

    /** Linhas do cronograma, da relação carregada quando houver (evita consulta por linha nas listas). */
    public function installmentsList(): Collection
    {
        return $this->relationLoaded('installments') ? $this->installments : $this->installments()->get();
    }

    /** Parcelas que já viraram card (inclui as de card excluído depois). */
    public function generatedInstallments(): Collection
    {
        return $this->installmentsList()->filter(fn (FixedCostInstallment $i) => $i->generated_at !== null)->values();
    }

    /** Parcelas do cronograma que ainda não viraram card, em ordem. */
    public function pendingInstallments(): Collection
    {
        return $this->installmentsList()->filter(fn (FixedCostInstallment $i) => $i->generated_at === null)->values();
    }

    public function generatedNumbers(): Collection
    {
        return $this->generatedInstallments()->pluck('number');
    }

    public function pendingNumbers(): Collection
    {
        return $this->pendingInstallments()->pluck('number');
    }

    public function nextPendingNumber(): ?int
    {
        return $this->pendingNumbers()->first();
    }

    public function maxGeneratedNumber(): int
    {
        return (int) $this->generatedNumbers()->max();
    }

    public function hasGeneratedInstallments(): bool
    {
        return $this->generatedNumbers()->isNotEmpty();
    }

    public function modeLabel(): string
    {
        return $this->generation_mode === FixedCostGenerationMode::LeadDays
            ? "{$this->lead_days} dia(s) antes"
            : 'Todas agora';
    }
}
