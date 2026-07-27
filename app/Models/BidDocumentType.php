<?php

namespace App\Models;

use App\Support\BidText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tipo canônico de documento (ver specs/21 §6.4). Os apelidos normalizados são o que permite
 * casar "CND Federal" do edital com "Certidão Negativa de Débitos Federais" do acervo.
 */
class BidDocumentType extends Model
{
    public const ATESTADO_SLUG = 'atestado_capacidade_tecnica';

    public const REGISTRO_PROFISSIONAL_SLUG = 'registro_crea_cau';

    protected $fillable = [
        'bid_document_category_id', 'slug', 'name', 'aliases', 'issuer',
        'default_validity_days', 'requires_control_code', 'essential', 'sort_order', 'active',
    ];

    protected $casts = [
        'aliases' => 'array',
        'requires_control_code' => 'boolean',
        'essential' => 'boolean',
        'active' => 'boolean',
        'default_validity_days' => 'integer',
        'sort_order' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(BidDocumentCategory::class, 'bid_document_category_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(BidDocument::class, 'bid_document_type_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /** Nome + apelidos, normalizados — usados pelo matcher e pela leitura de certidão. */
    public function normalizedNames(): array
    {
        $names = array_merge([$this->name], $this->aliases ?? []);

        return array_values(array_filter(array_map(
            fn ($n) => BidText::normalize($n),
            $names
        )));
    }
}
