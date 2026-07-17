<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CardTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'description', 'board_id', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CardTemplateItem::class)->orderBy('position');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
