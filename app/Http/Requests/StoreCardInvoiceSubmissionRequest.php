<?php

namespace App\Http\Requests;

use App\Rules\Cnpj;
use Illuminate\Foundation\Http\FormRequest;

/** Envio público do link de Solicitação de Nota — mesmas regras do formulário externo (specs/11). */
class StoreCardInvoiceSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // rota pública: quem libera é o token ativo, checado no controller
    }

    public function rules(): array
    {
        return [
            'cnpj' => ['required', 'string', new Cnpj],
            'name' => ['required', 'string', 'max:180'],
            'requester_name' => ['required', 'string', 'max:180'],
            'value' => ['required', 'string'],
            'service_date' => ['required', 'date'],
            'service_description' => ['required', 'string'],
            'payment_data' => ['required', 'string'],
            'invoice' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
        ];
    }

    public function attributes(): array
    {
        return [
            'cnpj' => 'CNPJ', 'name' => 'nome', 'requester_name' => 'nome do solicitante', 'value' => 'valor',
            'service_date' => 'data', 'service_description' => 'descrição do serviço',
            'payment_data' => 'dados para pagamento', 'invoice' => 'nota fiscal',
        ];
    }
}
