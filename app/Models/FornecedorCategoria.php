<?php

namespace App\Models;

use App\Domain\Enums\UnidadeMedida;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FornecedorCategoria extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['nome', 'unidade', 'active'];

    protected $casts = [
        'active' => 'boolean',
        'unidade' => UnidadeMedida::class,
    ];

    public function fornecedores(): HasMany
    {
        return $this->hasMany(Fornecedor::class);
    }

    public function priceRecords(): HasMany
    {
        return $this->hasMany(PriceRecord::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
