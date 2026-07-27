<?php

namespace App\Models;

use App\Domain\Enums\BidDocumentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Documento de habilitação de uma empresa licitante (ver specs/21 §6.5).
 *
 * O status de vigência é SEMPRE calculado (§10.1) — não existe coluna `status`, justamente para
 * a informação nunca ficar velha e não depender de cron.
 */
class BidDocument extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'bid_company_id', 'bid_document_category_id', 'bid_document_type_id', 'name',
        'control_code', 'issuer', 'issued_at', 'expires_at', 'no_expiry',
        'file_path', 'original_name', 'mime_type', 'file_size',
        'ai_extracted', 'ai_confidence', 'notes', 'supersedes_id', 'superseded_at', 'uploaded_by',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'expires_at' => 'date',
        'no_expiry' => 'boolean',
        'superseded_at' => 'datetime',
        'ai_extracted' => 'array',
        'ai_confidence' => 'decimal:3',
        'file_size' => 'integer',
    ];

    // Relacionamentos -------------------------------------------------------

    public function company(): BelongsTo
    {
        return $this->belongsTo(BidCompany::class, 'bid_company_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BidDocumentCategory::class, 'bid_document_category_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(BidDocumentType::class, 'bid_document_type_id');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // Vigência --------------------------------------------------------------

    public static function expiringDays(): int
    {
        return (int) config('licitacoes.expiring_days', 30);
    }

    public static function criticalDays(): int
    {
        return (int) config('licitacoes.critical_days', 7);
    }

    /** Dias até vencer (negativo = já venceu). Null quando não tem validade. */
    protected function daysToExpire(): Attribute
    {
        return Attribute::make(get: function (): ?int {
            if ($this->no_expiry || ! $this->expires_at) {
                return null;
            }

            return Carbon::today()->diffInDays($this->expires_at, false);
        });
    }

    protected function status(): Attribute
    {
        return Attribute::make(get: function (): BidDocumentStatus {
            $days = $this->days_to_expire;

            return match (true) {
                $days === null => BidDocumentStatus::Permanente,
                $days < 0 => BidDocumentStatus::Vencido,
                $days <= self::expiringDays() => BidDocumentStatus::Vencendo,
                default => BidDocumentStatus::Valido,
            };
        });
    }

    /** Vence em `critical_days` ou menos — merece destaque extra na UI e pesa menos no score. */
    protected function isCritical(): Attribute
    {
        return Attribute::make(get: function (): bool {
            $days = $this->days_to_expire;

            return $days !== null && $days >= 0 && $days <= self::criticalDays();
        });
    }

    /** Rótulo pronto para exibição ("Vence em 12 dias", "Vencido há 3 dias"). */
    protected function statusLabel(): Attribute
    {
        return Attribute::make(get: function (): string {
            $days = $this->days_to_expire;

            return match (true) {
                $days === null => 'Sem validade',
                $days < 0 => 'Vencido há '.abs($days).' '.(abs($days) === 1 ? 'dia' : 'dias'),
                $days === 0 => 'Vence hoje',
                $days <= self::expiringDays() => 'Vence em '.$days.' '.($days === 1 ? 'dia' : 'dias'),
                default => 'Válido — faltam '.$days.' dias',
            };
        });
    }

    // Scopes ----------------------------------------------------------------

    /** Documentos vigentes: não substituídos (e, por SoftDeletes, não excluídos). */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('superseded_at');
    }

    /** Ainda serve para habilitação hoje: permanente, válido ou vencendo (não vencido). */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->where('no_expiry', true)
            ->orWhere('expires_at', '>=', Carbon::today()->toDateString()));
    }

    /**
     * Filtro de status em SQL (as listas e contadores são server-side).
     * "valido" inclui os permanentes — é o que o usuário espera ver na aba "Válidos".
     */
    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        $today = Carbon::today()->toDateString();
        $limit = Carbon::today()->addDays(self::expiringDays())->toDateString();

        return match ($status) {
            'valido' => $query->where(fn ($q) => $q->where('no_expiry', true)->orWhere('expires_at', '>', $limit)),
            'vencendo' => $query->where('no_expiry', false)
                ->whereBetween('expires_at', [$today, $limit]),
            'vencido' => $query->where('no_expiry', false)->where('expires_at', '<', $today),
            'permanente' => $query->where('no_expiry', true),
            default => $query,
        };
    }

    /** Expressões agregadas de contagem por status — uma única query no painel (§13). */
    public static function countExpression(string $status): string
    {
        $today = Carbon::today()->toDateString();
        $limit = Carbon::today()->addDays(self::expiringDays())->toDateString();

        return match ($status) {
            'valido' => "SUM(CASE WHEN no_expiry = 1 OR expires_at > '{$limit}' THEN 1 ELSE 0 END)",
            'vencendo' => "SUM(CASE WHEN no_expiry = 0 AND expires_at >= '{$today}' AND expires_at <= '{$limit}' THEN 1 ELSE 0 END)",
            'vencido' => "SUM(CASE WHEN no_expiry = 0 AND expires_at < '{$today}' THEN 1 ELSE 0 END)",
            default => 'COUNT(*)',
        };
    }
}
