<?php

namespace App\Models;

use App\Domain\Enums\BidNoticeSource;
use App\Domain\Enums\BidNoticeStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Edital enviado para análise (ver specs/21 §6.6). */
class BidNotice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title', 'status', 'source', 'file_path', 'original_name', 'mime_type', 'file_size',
        'raw_text', 'agency', 'number', 'process_number', 'modality', 'portal', 'uf', 'city',
        'object_summary', 'estimated_value', 'session_at', 'proposal_deadline_at',
        'me_epp_exclusive', 'requires_site_visit', 'requires_bid_bond',
        'ai_confidence', 'ai_warnings', 'raw_response', 'prompt_version', 'error_message',
        'analyzed_at', 'created_by',
    ];

    protected $casts = [
        'status' => BidNoticeStatus::class,
        'source' => BidNoticeSource::class,
        'estimated_value' => 'decimal:2',
        'session_at' => 'datetime',
        'proposal_deadline_at' => 'datetime',
        'analyzed_at' => 'datetime',
        'me_epp_exclusive' => 'boolean',
        'requires_site_visit' => 'boolean',
        'requires_bid_bond' => 'boolean',
        'ai_confidence' => 'decimal:3',
        'ai_warnings' => 'array',
        'file_size' => 'integer',
    ];

    public function requirements(): HasMany
    {
        return $this->hasMany(BidNoticeRequirement::class, 'bid_notice_id')->orderBy('sort_order');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(BidNoticeEvaluation::class, 'bid_notice_id')->orderBy('rank');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Análise interrompida: ficou em `processando` além de `stale_minutes` (ver specs/21 §5.2).
     * Sem worker, é assim que uma aba fechada ou um timeout do servidor web se torna visível —
     * o registro é exibido como interrompido e oferece Reprocessar.
     */
    protected function isStale(): Attribute
    {
        return Attribute::make(get: fn (): bool => $this->status === BidNoticeStatus::Processando
            && $this->updated_at !== null
            && $this->updated_at->diffInMinutes(now()) >= (int) config('licitacoes.stale_minutes', 10));
    }

    /** Confiança da leitura em rótulo legível. */
    protected function confidenceLabel(): Attribute
    {
        return Attribute::make(get: function (): string {
            $value = (float) $this->ai_confidence;

            return match (true) {
                $this->ai_confidence === null => 'não informada',
                $value >= 0.8 => 'alta',
                $value >= 0.5 => 'média',
                default => 'baixa',
            };
        });
    }

    /** A avaliação está velha em relação ao acervo? Dispara o banner de recálculo (§9.6). */
    public function acervoChangedAfterEvaluation(): bool
    {
        $evaluatedAt = $this->evaluations()->max('evaluated_at');

        if (! $evaluatedAt) {
            return false;
        }

        $companyIds = $this->evaluations()->pluck('bid_company_id');

        return BidDocument::withTrashed()
            ->whereIn('bid_company_id', $companyIds)
            ->where('updated_at', '>', $evaluatedAt)
            ->exists();
    }
}
