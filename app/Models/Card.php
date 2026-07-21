<?php

namespace App\Models;

use App\Domain\Enums\CardOrigin;
use App\Domain\Enums\CardPriority;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Card extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'board_id', 'board_column_id', 'empresa_id', 'fornecedor_id', 'event_id', 'assignee_id', 'created_by',
        'title', 'description', 'estimated_value', 'actual_value', 'due_date',
        'priority', 'origin', 'position', 'concluded_at', 'concluded_by', 'archived_at', 'archived_by',
    ];

    protected $casts = [
        'estimated_value' => 'decimal:2',
        'actual_value' => 'decimal:2',
        'due_date' => 'date',
        'priority' => CardPriority::class,
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
}
