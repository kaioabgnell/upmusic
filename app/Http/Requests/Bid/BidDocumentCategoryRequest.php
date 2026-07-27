<?php

namespace App\Http\Requests\Bid;

use App\Models\BidDocumentCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Categoria de documento (ver specs/21 §9.8). Store e update compartilham o mesmo Form Request
 * porque as regras são idênticas — só a unicidade ignora o próprio registro.
 */
class BidDocumentCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category
            ? $this->user()->can('update', $category)
            : $this->user()->can('create', BidDocumentCategory::class);
    }

    public function rules(): array
    {
        $category = $this->route('category');

        return [
            'name' => ['required', 'string', 'max:60'],
            // Slug das categorias nativas é imutável: é o contrato com a IA (§6.3).
            'slug' => $category?->system
                ? ['nullable']
                : ['required', 'string', 'max:30', 'regex:/^[a-z0-9_]+$/',
                    Rule::unique('bid_document_categories', 'slug')->ignore($category?->id)],
            'color' => ['nullable', 'string', 'max:7'],
            'icon' => ['nullable', 'string', 'max:40'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $category = $this->route('category');

        $this->merge([
            'active' => $this->boolean('active'),
            'slug' => $category?->system
                ? $category->slug
                : Str::slug((string) $this->input('slug', $this->input('name')), '_'),
        ]);
    }

    public function attributes(): array
    {
        return ['name' => 'nome', 'slug' => 'identificador', 'icon' => 'ícone'];
    }
}
