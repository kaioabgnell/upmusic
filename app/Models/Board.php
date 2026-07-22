<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Board extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'setor_id', 'name', 'description', 'color', 'icon', 'position', 'active', 'allows_supplier_form',
    ];

    protected $casts = [
        'active' => 'boolean',
        'position' => 'integer',
        'allows_supplier_form' => 'boolean',
    ];

    public function setor(): BelongsTo
    {
        return $this->belongsTo(Setor::class);
    }

    public function columns(): HasMany
    {
        return $this->hasMany(BoardColumn::class)->orderBy('position');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(BoardField::class)->orderBy('position');
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_board');
    }

    public function externalForms(): HasMany
    {
        return $this->hasMany(ExternalForm::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
