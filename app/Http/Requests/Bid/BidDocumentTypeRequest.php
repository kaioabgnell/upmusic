<?php

namespace App\Http\Requests\Bid;

use App\Models\BidDocumentType;
use App\Support\BidText;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Tipo canônico de documento e seus apelidos (ver specs/21 §9.8). */
class BidDocumentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $type = $this->route('type');

        return $type
            ? $this->user()->can('update', $type)
            : $this->user()->can('create', BidDocumentType::class);
    }

    public function rules(): array
    {
        $type = $this->route('type');

        return [
            'bid_document_category_id' => ['required', 'exists:bid_document_categories,id'],
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('bid_document_types', 'slug')->ignore($type?->id)],
            'aliases' => ['array'],
            'aliases.*' => ['string', 'max:150'],
            'issuer' => ['nullable', 'string', 'max:120'],
            'default_validity_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'requires_control_code' => ['boolean'],
            'essential' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Apelidos chegam como texto separado por vírgula/linha e são normalizados aqui — é o que
        // o matcher compara, então precisa entrar no banco já sem acento e em minúsculas.
        $raw = $this->input('aliases');

        $aliases = is_array($raw)
            ? $raw
            : preg_split('/[\r\n,;]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY);

        $this->merge([
            'slug' => Str::slug((string) $this->input('slug', $this->input('name')), '_'),
            'aliases' => array_values(array_unique(array_filter(array_map(
                fn ($alias) => BidText::normalize($alias),
                $aliases ?: []
            )))),
            'requires_control_code' => $this->boolean('requires_control_code'),
            'essential' => $this->boolean('essential'),
            'active' => $this->boolean('active'),
        ]);
    }

    public function attributes(): array
    {
        return [
            'bid_document_category_id' => 'categoria',
            'name' => 'nome',
            'slug' => 'identificador',
            'aliases' => 'apelidos',
            'default_validity_days' => 'validade padrão',
        ];
    }
}
