<?php

namespace App\Models;

use App\Domain\Enums\BidMatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Conferência de um requisito para uma empresa (ver specs/21 §6.9). */
class BidRequirementMatch extends Model
{
    protected $fillable = [
        'bid_notice_requirement_id', 'bid_company_id', 'bid_document_id',
        'status', 'confidence', 'reason', 'manual_override', 'overridden_by',
    ];

    protected $casts = [
        'status' => BidMatchStatus::class,
        'manual_override' => 'boolean',
    ];

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(BidNoticeRequirement::class, 'bid_notice_requirement_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(BidCompany::class, 'bid_company_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(BidDocument::class, 'bid_document_id');
    }
}
