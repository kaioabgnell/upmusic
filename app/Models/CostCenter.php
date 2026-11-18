<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * Centro de custo (specs/25) — cadastro global, opcional nas linhas de CUSTOS e RECEITAS.
 *
 * Inativo some das escolhas novas, mas continua aparecendo (e válido) nas linhas que já o usam.
 * Em uso não pode ser excluído; sem uso vai para a lixeira (soft delete).
 */
class CostCenter extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['code', 'name', 'description', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function costItems(): HasMany
    {
        return $this->hasMany(FinanceCostItem::class);
    }

    public function revenues(): HasMany
    {
        return $this->hasMany(FinanceRevenue::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /** Por código e depois nome; os sem código vão para o fim. */
    public function scopeOrdered($query)
    {
        return $query->orderByRaw('code IS NULL')->orderBy('code')->orderBy('name');
    }

    /** "MKT-01 Marketing", ou só "Marketing" quando não há código. */
    public function getLabelAttribute(): string
    {
        return trim(($this->code ? $this->code.' ' : '').$this->name);
    }

    /** Quantas linhas (custos + receitas não excluídas) usam este centro. */
    public function usageCount(): int
    {
        return $this->costItems()->count() + $this->revenues()->count();
    }

    /**
     * Opções dos selects: todos os ativos + os já usados nas linhas exibidas (mesmo inativos ou
     * excluídos), para a linha nunca "perder" o centro que tem.
     *
     * @param  iterable<int|null>  $usedIds
     * @return Collection<int, array{id:int, label:string, active:bool}>
     */
    public static function options(iterable $usedIds = []): Collection
    {
        $usedIds = collect($usedIds)->filter()->unique()->values();

        return static::withTrashed()
            ->where(fn ($q) => $q->where(fn ($a) => $a->where('active', true)->whereNull('deleted_at'))
                ->when($usedIds->isNotEmpty(), fn ($w) => $w->orWhereIn('id', $usedIds)))
            ->ordered()
            ->get()
            ->map(fn (self $c) => [
                'id' => $c->id,
                'label' => $c->label.($c->active && ! $c->trashed() ? '' : ' (inativo)'),
                'active' => $c->active && ! $c->trashed(),
            ])
            ->values();
    }
}
