<?php

namespace App\Http\Requests\Rentals;

use App\Domain\Enums\RentalItemDeletionReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Exclusão lógica de item com motivo obrigatório (specs/24 §2 Q11-Q22). */
class DestroyRentalItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // autorização real na policy do controller (RentalItemPolicy::delete)
    }

    public function rules(): array
    {
        return [
            'deletion_reason' => ['required', Rule::in(array_column(RentalItemDeletionReason::cases(), 'value'))],
            'deletion_reason_other' => ['required_if:deletion_reason,outros', 'nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'deletion_reason' => 'motivo',
            'deletion_reason_other' => 'descrição do motivo',
        ];
    }
}
