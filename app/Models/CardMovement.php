<?php

namespace App\Models;

use App\Domain\Enums\MovementType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'card_id', 'user_id', 'from_board_id', 'to_board_id',
        'from_column_id', 'to_column_id', 'type', 'note',
    ];

    protected $casts = ['type' => MovementType::class];

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fromColumn(): BelongsTo
    {
        return $this->belongsTo(BoardColumn::class, 'from_column_id');
    }

    public function toColumn(): BelongsTo
    {
        return $this->belongsTo(BoardColumn::class, 'to_column_id');
    }

    public function fromBoard(): BelongsTo
    {
        return $this->belongsTo(Board::class, 'from_board_id');
    }

    public function toBoard(): BelongsTo
    {
        return $this->belongsTo(Board::class, 'to_board_id');
    }
}
