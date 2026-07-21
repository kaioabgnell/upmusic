<?php

namespace App\Models;

use App\Domain\Enums\AttachmentKind;
use App\Domain\Enums\CaptureSource;
use App\Domain\Enums\CaptureStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardCapture extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'board_id', 'card_id', 'kind', 'source', 'status',
        'original_name', 'path', 'mime', 'size', 'suggested_title',
    ];

    protected $casts = [
        'kind' => AttachmentKind::class,
        'source' => CaptureSource::class,
        'status' => CaptureStatus::class,
        'size' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', CaptureStatus::Pendente->value);
    }
}
