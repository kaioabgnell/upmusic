<?php

namespace App\Models;

use App\Domain\Enums\PessoaTipo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fornecedor extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'fornecedores';

    protected $fillable = [
        'type', 'name', 'document', 'email', 'phone', 'fornecedor_categoria_id', 'notes', 'active',
    ];

    protected $casts = [
        'type' => PessoaTipo::class,
        'active' => 'boolean',
    ];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(FornecedorCategoria::class, 'fornecedor_categoria_id');
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
