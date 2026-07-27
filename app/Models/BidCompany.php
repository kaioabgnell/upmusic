<?php

namespace App\Models;

use App\Domain\Enums\BidCompanySize;
use App\Support\Br;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Empresa licitante do grupo (ver specs/21 §6.1) — não confundir com `Empresa`, que é o
 * cadastro de clientes usado por cards, financeiro e banco de preços.
 */
class BidCompany extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'corporate_name', 'trade_name', 'cnpj', 'size', 'capital_social', 'net_worth',
        'tax_regime', 'cnaes', 'responsible_name', 'email', 'phone',
        'zipcode', 'address', 'number', 'complement', 'district', 'city', 'state',
        'color', 'notes', 'active',
    ];

    protected $casts = [
        'size' => BidCompanySize::class,
        'cnaes' => 'array',
        'capital_social' => 'decimal:2',
        'net_worth' => 'decimal:2',
        'active' => 'boolean',
    ];

    // Relacionamentos -------------------------------------------------------

    public function documents(): HasMany
    {
        return $this->hasMany(BidDocument::class, 'bid_company_id');
    }

    /** Só o acervo vigente (documentos não substituídos). */
    public function currentDocuments(): HasMany
    {
        return $this->documents()->whereNull('superseded_at');
    }

    public function businessLines(): BelongsToMany
    {
        return $this->belongsToMany(BidBusinessLine::class, 'bid_company_business_line');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(BidNoticeEvaluation::class, 'bid_company_id');
    }

    // Apresentação ----------------------------------------------------------

    protected function displayName(): Attribute
    {
        return Attribute::make(get: fn () => $this->trade_name ?: $this->corporate_name);
    }

    protected function cnpjFormatted(): Attribute
    {
        return Attribute::make(get: fn () => $this->cnpj ? Br::formatCnpj($this->cnpj) : '—');
    }

    /** Iniciais para o avatar da empresa na matriz e nos cards. */
    protected function initials(): Attribute
    {
        return Attribute::make(get: function () {
            $parts = preg_split('/\s+/', trim((string) $this->display_name));
            $first = mb_strtoupper(mb_substr($parts[0] ?? '?', 0, 1));

            return count($parts) > 1 ? $first.mb_strtoupper(mb_substr(end($parts), 0, 1)) : $first;
        });
    }

    /** CNAEs comparáveis (classe de 5 dígitos) — usados pelo matcher. */
    public function cnaeClasses(): array
    {
        return array_values(array_filter(array_map(
            fn ($cnae) => \App\Support\BidText::cnaeClass($cnae['code'] ?? null),
            $this->cnaes ?? []
        )));
    }

    public function primaryCnae(): ?string
    {
        foreach ($this->cnaes ?? [] as $cnae) {
            if ($cnae['primary'] ?? false) {
                return $cnae['code'] ?? null;
            }
        }

        return $this->cnaes[0]['code'] ?? null;
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
