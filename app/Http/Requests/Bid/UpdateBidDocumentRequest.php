<?php

namespace App\Http\Requests\Bid;

/**
 * Edição dos metadados de um documento (sem trocar o arquivo — para isso existe "Renovar", que
 * cria uma nova versão em vez de reescrever o histórico).
 */
class UpdateBidDocumentRequest extends StoreBidDocumentRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('document'));
    }

    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['arquivo'], $rules['ai_extracted'], $rules['ai_confidence']);

        return $rules;
    }
}
