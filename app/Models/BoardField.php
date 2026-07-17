<?php

namespace App\Models;

use App\Domain\Enums\FieldType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoardField extends Model
{
    use HasFactory;

    protected $fillable = [
        'board_id', 'label', 'type', 'options', 'required', 'position',
    ];

    protected $casts = [
        'type' => FieldType::class,
        'options' => 'array',
        'required' => 'boolean',
        'position' => 'integer',
    ];

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }
}
