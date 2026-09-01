<?php

namespace App\Models;

use App\Domain\Enums\EventTipo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'location', 'responsible_name', 'phone', 'email', 'tipo', 'start_date', 'end_date', 'active',
    ];

    protected $casts = [
        'tipo' => EventTipo::class,
        'start_date' => 'date',
        'end_date' => 'date',
        'active' => 'boolean',
    ];

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    /** Planilha financeira do evento (specs/23) — criada sob demanda, por isso pode não existir. */
    public function financeSheet(): HasOne
    {
        return $this->hasOne(FinanceSheet::class);
    }

    /** Coordenadores restritos a este evento (ver specs/20). */
    public function coordinators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_user');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
