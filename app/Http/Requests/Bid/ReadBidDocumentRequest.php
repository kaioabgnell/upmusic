<?php

namespace App\Http\Requests\Bid;

use App\Models\BidDocument;
use Illuminate\Foundation\Http\FormRequest;

/** Leitura assistida do documento pela IA (ver specs/21 §9.4) — não persiste nada. */
class ReadBidDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', BidDocument::class);
    }

    public function rules(): array
    {
        $max = (int) config('licitacoes.document_max_kb', 10240);

        return [
            'arquivo' => [
                'required', 'file', "max:{$max}",
                'mimes:pdf,jpg,jpeg,png',
                'mimetypes:application/pdf,image/jpeg,image/png',
            ],
            'bid_company_id' => ['nullable', 'exists:bid_companies,id'],
        ];
    }

    public function attributes(): array
    {
        return ['arquivo' => 'arquivo'];
    }
}
