<?php

namespace App\Models;

use App\Domain\Enums\CardPriority;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardTemplateItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'card_template_id', 'title', 'description', 'default_column_id', 'default_fields', 'position',
        'due_date', 'priority', 'default_assignee_id',
    ];

    protected $casts = [
        'default_fields' => 'array',
        'position' => 'integer',
        'due_date' => 'date',
        'priority' => CardPriority::class,
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(CardTemplate::class, 'card_template_id');
    }

    public function defaultColumn(): BelongsTo
    {
        return $this->belongsTo(BoardColumn::class, 'default_column_id');
    }

    public function defaultAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_assignee_id');
    }
}
