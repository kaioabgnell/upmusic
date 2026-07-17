<?php

namespace App\Models;

use App\Domain\Enums\PessoaTipo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Empresa extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'empresas';

    protected $fillable = [
        'corporate_name', 'trade_name', 'type', 'document', 'email', 'phone',
        'zipcode', 'address', 'number', 'complement', 'district', 'city', 'state',
        'notes', 'active',
    ];

    protected $casts = [
        'type' => PessoaTipo::class,
        'active' => 'boolean',
    ];

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    public function financialEntries(): HasMany
    {
        return $this->hasMany(FinancialEntry::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
