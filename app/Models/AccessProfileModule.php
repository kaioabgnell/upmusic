<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um módulo ligado num perfil (specs/28 §3). `module` fica como string, sem cast para o enum: um
 * valor que deixe de existir no código não pode derrubar a leitura do perfil.
 */
class AccessProfileModule extends Model
{
    protected $fillable = ['access_profile_id', 'module'];

    public function accessProfile(): BelongsTo
    {
        return $this->belongsTo(AccessProfile::class);
    }
}
