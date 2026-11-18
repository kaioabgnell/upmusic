<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Linha do cronograma de um custo fixo (specs/27 §2.3): uma por parcela, de "começar na parcela" até o
 * total. `generated_at` nulo = ainda sem card. Gerada, a linha guarda o card; se o card for excluído, a
 * parcela continua gerada (não volta sozinha) e pode ganhar um card novo pelo detalhe do custo.
 */
class FixedCostInstallment extends Model
{
    protected $fillable = ['fixed_cost_id', 'number', 'due_date', 'card_id', 'generated_at', 'generated_by'];

    protected $casts = [
        'number' => 'integer',
        'due_date' => 'immutable_date',
        'generated_at' => 'datetime',
    ];

    public function fixedCost(): BelongsTo
    {
        return $this->belongsTo(FixedCost::class)->withTrashed();
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class)->withTrashed();
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function isGenerated(): bool
    {
        return $this->generated_at !== null;
    }

    /** Gerada, mas o card foi excluído depois — é o caso do "Gerar card novamente". */
    public function cardDeleted(): bool
    {
        return $this->isGenerated() && ($this->card === null || $this->card->trashed());
    }

    /**
     * Paga = o card está concluído ou na etapa final do quadro (ex.: coluna "Concluído"). Parcela paga
     * não pode ser excluída (specs/27 §5.5).
     */
    public function isPaid(): bool
    {
        $card = $this->card;

        if (! $card || $card->trashed()) {
            return false;
        }

        return $card->concluded_at !== null || (bool) $card->column?->is_final;
    }
}
