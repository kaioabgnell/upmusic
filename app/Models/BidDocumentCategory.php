<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Categoria de documento do módulo de Licitações (ver specs/21 §6.3). */
class BidDocumentCategory extends Model
{
    /** Slugs nativos — contrato com a IA (enum do responseSchema). Não podem ser excluídos. */
    public const SYSTEM_SLUGS = ['fiscal', 'trabalhista', 'juridica', 'tecnica', 'financeira', 'outros'];

    public const FALLBACK_SLUG = 'outros';

    protected $fillable = ['slug', 'name', 'color', 'icon', 'sort_order', 'system', 'active'];

    protected $casts = [
        'system' => 'boolean',
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(BidDocument::class);
    }

    public function types(): HasMany
    {
        return $this->hasMany(BidDocumentType::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
