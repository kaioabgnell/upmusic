<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Link único por card para o fornecedor enviar a própria minuta (ver specs/19). A existência do
 * registro (com active = true) é o que habilita a URL pública /minuta/{token}.
 */
class CardSupplierForm extends Model
{
    use HasFactory;

    protected $fillable = ['card_id', 'token', 'active', 'created_by'];

    protected $casts = ['active' => 'boolean'];

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(CardSupplierSubmission::class)->latest();
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }
}
