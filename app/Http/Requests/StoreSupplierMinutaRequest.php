<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Envio da minuta pelo fornecedor via link público (ver specs/19). Sem auth: a rota é gated pelo
 * token do link (ver SupplierFormController), então authorize() libera aqui.
 */
class StoreSupplierMinutaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'minuta' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return ['minuta' => 'minuta', 'note' => 'observação'];
    }

    public function messages(): array
    {
        return ['minuta.required' => 'É obrigatório o envio da minuta.'];
    }
}
