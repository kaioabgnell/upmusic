<?php

namespace App\Models;

use App\Domain\Enums\CardColor;
use App\Domain\Enums\CardNegociado;
use App\Domain\Enums\CardOrigin;
use App\Domain\Enums\CardPriority;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Card extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'board_id', 'board_column_id', 'empresa_id', 'fornecedor_id', 'event_id', 'fixed_cost_installment_id', 'assignee_id', 'created_by',
        'title', 'description', 'estimated_value', 'actual_value', 'unit_value', 'quantity',
        'valor_sem_nota', 'valor_com_nota', 'negociado', 'due_date',
        'priority', 'color', 'origin', 'position', 'concluded_at', 'concluded_by', 'archived_at', 'archived_by',
    ];

    protected $casts = [
        'estimated_value' => 'decimal:2',
        'actual_value' => 'decimal:2',
        'unit_value' => 'decimal:2',
        'quantity' => 'decimal:2',
        'valor_sem_nota' => 'decimal:2',
        'valor_com_nota' => 'decimal:2',
        'negociado' => CardNegociado::class,
        'due_date' => 'date',
        'priority' => CardPriority::class,
        'color' => CardColor::class,
        'origin' => CardOrigin::class,
        'position' => 'integer',
        'concluded_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function column(): BelongsTo
    {
        return $this->belongsTo(BoardColumn::class, 'board_column_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** Parcela de custo fixo que gerou este card (specs/27) — alimenta o selo "Parcela X de Y". */
    public function fixedCostInstallment(): BelongsTo
    {
        return $this->belongsTo(FixedCostInstallment::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function concludedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'concluded_by');
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('concluded_at');
    }

    public function scopeConcluded($query)
    {
        return $query->whereNotNull('concluded_at');
    }

    /**
     * Restringe os cards aos eventos visíveis do usuário (specs/20 e specs/28 §6). Sem restrição
     * (allowedEventIds() === null), não filtra nada. Com restrição, mostra só os cards dos eventos
     * dele — card sem evento fica de fora — **exceto no quadro de custos fixos**: custo fixo é da
     * empresa e nunca tem evento, então ali a restrição não se aplica (o acesso ao quadro continua
     * dependendo do módulo Custos fixos).
     */
    public function scopeVisibleTo($query, User $user)
    {
        $ids = $user->allowedEventIds();

        if ($ids === null) {
            return $query;
        }

        $eventColumn = $query->qualifyColumn('event_id');
        $boardColumn = $query->qualifyColumn('board_id');

        return $query->where(fn ($q) => $q
            ->whereIn($eventColumn, $ids)
            ->orWhereIn($boardColumn, Board::query()->fixedCosts()->select('id')));
    }

    public function scopeArchived($query)
    {
        return $query->whereNotNull('archived_at');
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(CardFieldValue::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(CardAttachment::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(CardComment::class)->latest();
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CardMovement::class)->latest();
    }

    public function supplierForm(): HasOne
    {
        return $this->hasOne(CardSupplierForm::class);
    }

    /** Link público de Solicitação de Nota deste card. */
    public function invoiceRequest(): HasOne
    {
        return $this->hasOne(CardInvoiceRequest::class);
    }

    /**
     * Linha de custo criada a partir deste card no Financeiro do Evento (specs/23). Um card gera
     * no máximo uma linha — a ponte reusa a existente em vez de duplicar.
     */
    public function financeCostItem(): HasOne
    {
        return $this->hasOne(FinanceCostItem::class);
    }

    /**
     * Envio do formulário externo que originou o card (specs/11) — null nos cards criados na mão.
     */
    public function externalSubmission(): HasOne
    {
        return $this->hasOne(ExternalSubmission::class);
    }
}
