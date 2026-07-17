<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardFieldValue extends Model
{
    use HasFactory;

    protected $fillable = ['card_id', 'board_field_id', 'value'];

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(BoardField::class, 'board_field_id');
    }
}
