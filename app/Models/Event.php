<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'location', 'responsible_name', 'phone', 'email', 'start_date', 'end_date', 'active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'active' => 'boolean',
    ];

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
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
