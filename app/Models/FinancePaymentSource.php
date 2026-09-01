<?php

namespace App\Models;

use App\Domain\Enums\FinancePaymentSourceKind;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Grupo de pagamento (specs/23 §4.2): Caixa do Evento, Sócio 1, Ticketeira, Bar... */
class FinancePaymentSource extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'kind', 'user_id', 'active', 'position'];

    protected $casts = [
        'kind' => FinancePaymentSourceKind::class,
        'active' => 'boolean',
        'position' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FinancePayment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('position')->orderBy('id');
    }
}
