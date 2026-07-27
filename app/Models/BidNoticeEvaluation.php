<?php

namespace App\Models;

use App\Domain\Enums\BidVerdict;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Aptidão de uma empresa para um edital (ver specs/21 §6.8). */
class BidNoticeEvaluation extends Model
{
    protected $fillable = [
        'bid_notice_id', 'bid_company_id', 'verdict', 'score', 'rank',
        'met_count', 'expiring_count', 'missing_count', 'review_count',
        'blockers', 'highlights', 'verdict_at_analysis', 'score_at_analysis', 'evaluated_at',
    ];

    protected $casts = [
        'verdict' => BidVerdict::class,
        'verdict_at_analysis' => BidVerdict::class,
        'score' => 'decimal:2',
        'score_at_analysis' => 'decimal:2',
        'blockers' => 'array',
        'highlights' => 'array',
        'evaluated_at' => 'datetime',
        'rank' => 'integer',
    ];

    public function notice(): BelongsTo
    {
        return $this->belongsTo(BidNotice::class, 'bid_notice_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(BidCompany::class, 'bid_company_id');
    }
}
