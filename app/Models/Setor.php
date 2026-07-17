<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Setor extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'setores';

    protected $fillable = ['nome', 'descricao', 'color', 'icon', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function boards(): HasMany
    {
        return $this->hasMany(Board::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
