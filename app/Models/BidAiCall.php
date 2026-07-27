<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Log de cada chamada à IA — auditoria de custo e diagnóstico (ver specs/21 §6.10). */
class BidAiCall extends Model
{
    protected $fillable = [
        'type', 'related_type', 'related_id', 'model', 'prompt_version',
        'prompt_tokens', 'output_tokens', 'total_tokens', 'latency_ms',
        'success', 'http_status', 'error_message', 'user_id',
    ];

    protected $casts = [
        'success' => 'boolean',
        'prompt_tokens' => 'integer',
        'output_tokens' => 'integer',
        'total_tokens' => 'integer',
        'latency_ms' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
