<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Renovar custo fixo: quantas parcelas a mais (specs/27 §5.4). */
class RenewFixedCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('fixedCost'));
    }

    public function rules(): array
    {
        return ['extra' => ['required', 'integer', 'min:1', 'max:360']];
    }

    public function attributes(): array
    {
        return ['extra' => 'parcelas a mais'];
    }
}
