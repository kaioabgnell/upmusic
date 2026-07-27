<?php

namespace App\Http\Requests\Bid;

use App\Models\BidNotice;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Envio de edital para análise (ver specs/21 §9.5 e §10.5): arquivo (PDF/imagem) OU texto colado.
 */
class StoreBidNoticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', BidNotice::class);
    }

    public function rules(): array
    {
        $max = (int) config('licitacoes.notice_max_kb', 15360);
        $min = (int) config('licitacoes.notice_text_min', 200);
        $textMax = (int) config('licitacoes.notice_text_max', 50000);

        return [
            'arquivo' => [
                'required_without:raw_text', 'nullable', 'file', "max:{$max}",
                'mimes:pdf,jpg,jpeg,png',
                'mimetypes:application/pdf,image/jpeg,image/png',
            ],
            'raw_text' => ['required_without:arquivo', 'nullable', 'string', "min:{$min}", "max:{$textMax}"],
            'title' => ['nullable', 'string', 'max:200'],
            'companies' => ['array'],
            'companies.*' => ['exists:bid_companies,id'],
        ];
    }

    public function attributes(): array
    {
        return ['arquivo' => 'arquivo do edital', 'raw_text' => 'texto do edital'];
    }

    public function messages(): array
    {
        return [
            'arquivo.required_without' => 'Envie o arquivo do edital ou cole o texto.',
            'raw_text.required_without' => 'Cole o texto do edital ou envie o arquivo.',
            'raw_text.min' => 'O texto colado está curto demais para uma análise confiável.',
            'arquivo.max' => 'O arquivo do edital passou do limite. Envie um PDF menor ou cole o texto.',
        ];
    }
}
