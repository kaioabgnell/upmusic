<?php

namespace App\Models;

use App\Domain\Enums\ExternalSubmissionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_form_id', 'empresa_id', 'card_id', 'cnpj', 'name', 'value',
        'service_date', 'service_description', 'payment_data', 'invoice_path', 'status', 'ip',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'service_date' => 'date',
        'status' => ExternalSubmissionStatus::class,
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(ExternalForm::class, 'external_form_id');
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
