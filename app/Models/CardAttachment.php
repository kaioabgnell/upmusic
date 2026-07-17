<?php

namespace App\Models;

use App\Domain\Enums\AttachmentKind;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'card_id', 'uploaded_by', 'kind', 'original_name', 'path', 'mime', 'size',
    ];

    protected $casts = [
        'kind' => AttachmentKind::class,
        'size' => 'integer',
    ];

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
