<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Descrição pré-cadastrada de item de custo, por categoria (specs/23 §4.8). */
class FinanceItemPreset extends Model
{
    use HasFactory;

    protected $fillable = ['fornecedor_categoria_id', 'description', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(FornecedorCategoria::class, 'fornecedor_categoria_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
