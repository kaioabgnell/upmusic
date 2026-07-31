<?php

namespace App\Models;

use App\Domain\Enums\NotificationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Notificação interna de um único destinatário (specs/22). Um evento que precise avisar N pessoas
 * gera N linhas — assim `read_at` é por pessoa, sem tabela pivô.
 */
class UserNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'actor_id', 'card_id', 'board_id', 'type', 'data', 'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'type' => NotificationType::class,
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** withTrashed: o autor pode ter sido removido depois; o histórico continua legível. */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }
}
