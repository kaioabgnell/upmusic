<?php

namespace App\Models;

use App\Domain\Enums\BidRequirementKind;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Requisito de habilitação extraído do edital (ver specs/21 §6.7). */
class BidNoticeRequirement extends Model
{
    protected $fillable = [
        'bid_notice_id', 'kind', 'bid_document_category_id', 'bid_document_type_id',
        'name', 'description', 'mandatory', 'expected', 'source_excerpt', 'source_page',
        'ignored', 'ignored_reason', 'sort_order',
    ];

    protected $casts = [
        'kind' => BidRequirementKind::class,
        'mandatory' => 'boolean',
        'ignored' => 'boolean',
        'expected' => 'array',
        'source_page' => 'integer',
        'sort_order' => 'integer',
    ];

    public function notice(): BelongsTo
    {
        return $this->belongsTo(BidNotice::class, 'bid_notice_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BidDocumentCategory::class, 'bid_document_category_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(BidDocumentType::class, 'bid_document_type_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(BidRequirementMatch::class, 'bid_notice_requirement_id');
    }

    /** Peso no score: obrigatório vale 3, opcional 1 (ver specs/21 §10.4). */
    protected function weight(): Attribute
    {
        return Attribute::make(get: fn (): int => $this->mandatory ? 3 : 1);
    }

    /** Valor mínimo exigido, resolvendo percentual sobre o valor estimado do edital. */
    public function requiredAmount(?float $estimatedValue): ?float
    {
        $expected = $this->expected ?? [];

        if (isset($expected['numeric_min'])) {
            return (float) $expected['numeric_min'];
        }

        if (isset($expected['percent_of_estimate']) && $estimatedValue) {
            return round($estimatedValue * ((float) $expected['percent_of_estimate'] / 100), 2);
        }

        return null;
    }
}
