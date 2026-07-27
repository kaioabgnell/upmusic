<?php

namespace App\Http\Requests\Bid;

use App\Models\BidBusinessLine;
use App\Support\BidText;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Ramo de atuação e as palavras-chave que o ligam ao objeto do edital (ver specs/21 §9.8). */
class BidBusinessLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        $line = $this->route('line');

        return $line
            ? $this->user()->can('update', $line)
            : $this->user()->can('create', BidBusinessLine::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120',
                Rule::unique('bid_business_lines', 'name')->whereNull('deleted_at')->ignore($this->route('line')?->id)],
            'keywords' => ['array'],
            'keywords.*' => ['string', 'max:60'],
            'active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $raw = $this->input('keywords');

        $keywords = is_array($raw)
            ? $raw
            : preg_split('/[\r\n,;]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY);

        $this->merge([
            'keywords' => array_values(array_unique(array_filter(array_map(
                fn ($keyword) => BidText::normalize($keyword),
                $keywords ?: []
            )))),
            'active' => $this->boolean('active'),
        ]);
    }

    public function attributes(): array
    {
        return ['name' => 'nome', 'keywords' => 'palavras-chave'];
    }
}
