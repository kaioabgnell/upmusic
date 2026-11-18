<?php

namespace App\Models;

use App\Domain\Enums\BankAccountType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * Conta bancária (specs/26) — cadastro global, de uma ou mais empresas. Identifica de onde sai
 * cada pagamento de custo e para onde entra cada receita do Financeiro do Evento.
 *
 * Inativa some das escolhas novas, mas continua aparecendo (e válida) onde já foi usada.
 * Em uso não pode ser excluída; sem uso vai para a lixeira (soft delete).
 */
class BankAccount extends Model
{
    use HasFactory, SoftDeletes;

    /** Sugestões do campo Banco (datalist). O campo continua livre para qualquer outro. */
    public const COMMON_BANKS = [
        '001 - Banco do Brasil',
        '004 - Banco do Nordeste',
        '033 - Santander',
        '041 - Banrisul',
        '070 - BRB',
        '077 - Inter',
        '104 - Caixa Econômica Federal',
        '208 - BTG Pactual',
        '212 - Banco Original',
        '237 - Bradesco',
        '260 - Nubank',
        '290 - PagBank',
        '323 - Mercado Pago',
        '336 - C6 Bank',
        '341 - Itaú Unibanco',
        '380 - PicPay',
        '422 - Safra',
        '655 - Banco BV',
        '748 - Sicredi',
        '756 - Sicoob',
    ];

    protected $fillable = ['bank', 'name', 'agency', 'account_number', 'account_type', 'active'];

    protected $casts = [
        'account_type' => BankAccountType::class,
        'active' => 'boolean',
    ];

    public function empresas(): BelongsToMany
    {
        return $this->belongsToMany(Empresa::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FinancePayment::class);
    }

    public function revenues(): HasMany
    {
        return $this->hasMany(FinanceRevenue::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('name')->orderBy('bank');
    }

    /** "Operacional (Ag. 1234 · 56789-0)" — nome primeiro: é como o financeiro chama a conta. */
    public function getLabelAttribute(): string
    {
        return "{$this->name} (Ag. {$this->agency} · {$this->account_number})";
    }

    /** Quantos lançamentos usam esta conta: pagamentos de custo + receitas não excluídas. */
    public function usageCount(): int
    {
        return $this->payments()->count() + $this->revenues()->count();
    }

    /**
     * Opções dos selects: todas as ativas + as já usadas nos lançamentos exibidos (mesmo inativas
     * ou excluídas), para o lançamento nunca "perder" a conta que tem.
     *
     * @param  iterable<int|null>  $usedIds
     * @return Collection<int, array{id:int, label:string, bank:string, active:bool}>
     */
    public static function options(iterable $usedIds = []): Collection
    {
        $usedIds = collect($usedIds)->filter()->unique()->values();

        return static::withTrashed()
            ->where(fn ($q) => $q->where(fn ($a) => $a->where('active', true)->whereNull('deleted_at'))
                ->when($usedIds->isNotEmpty(), fn ($w) => $w->orWhereIn('id', $usedIds)))
            ->ordered()
            ->get()
            ->map(fn (self $a) => [
                'id' => $a->id,
                'label' => $a->label.($a->active && ! $a->trashed() ? '' : ' (inativa)'),
                'bank' => $a->bank,
                'active' => $a->active && ! $a->trashed(),
            ])
            ->values();
    }
}
