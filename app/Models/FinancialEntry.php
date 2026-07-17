<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'financial_plan_id', 'card_id', 'empresa_id', 'description', 'category',
        'estimated_value', 'actual_value', 'estimated_date', 'actual_date',
    ];

    protected $casts = [
        'estimated_value' => 'decimal:2',
        'actual_value' => 'decimal:2',
        'estimated_date' => 'date',
        'actual_date' => 'date',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(FinancialPlan::class, 'financial_plan_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }
}
