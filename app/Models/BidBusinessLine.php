<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Ramo de atuação da empresa licitante (ver specs/21 §6.2). */
class BidBusinessLine extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'keywords', 'active'];

    protected $casts = [
        'keywords' => 'array',
        'active' => 'boolean',
    ];

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(BidCompany::class, 'bid_company_business_line');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
