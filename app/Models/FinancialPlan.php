<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinancialPlan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'empresa_id', 'period_year', 'period_month', 'notes'];

    protected $casts = [
        'period_year' => 'integer',
        'period_month' => 'integer',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(FinancialEntry::class);
    }
}
