<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'fornecedor_categoria_id', 'fornecedor_id', 'card_id', 'event_id',
        'price', 'reference_date', 'notes', 'created_by',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'reference_date' => 'date',
    ];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(FornecedorCategoria::class, 'fornecedor_categoria_id');
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
