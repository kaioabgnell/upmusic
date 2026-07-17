<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternalForm extends Model
{
    use HasFactory;

    protected $fillable = ['board_id', 'target_column_id', 'event_id', 'token', 'title', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function targetColumn(): BelongsTo
    {
        return $this->belongsTo(BoardColumn::class, 'target_column_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ExternalSubmission::class);
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }
}
