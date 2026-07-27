<?php

namespace App\Http\Requests\Bid;

use App\Domain\Enums\BidCompanySize;
use App\Models\BidCompany;
use App\Rules\Cnpj;
use App\Support\Br;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Cadastro de empresa licitante (ver specs/21 §9.2). */
class StoreBidCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', BidCompany::class);
    }

    public function rules(): array
    {
        return [
            'corporate_name' => ['required', 'string', 'max:180'],
            'trade_name' => ['nullable', 'string', 'max:180'],
            'cnpj' => ['required', 'string', new Cnpj, Rule::unique('bid_companies', 'cnpj')->whereNull('deleted_at')],
            'size' => ['required', Rule::in(array_column(BidCompanySize::cases(), 'value'))],
            'capital_social' => ['nullable', 'numeric', 'min:0'],
            'net_worth' => ['nullable', 'numeric', 'min:0'],
            'tax_regime' => ['nullable', 'string', 'max:40'],
            'cnaes' => ['array'],
            'cnaes.*.code' => ['required_with:cnaes.*.description', 'nullable', 'string', 'max:20'],
            'cnaes.*.description' => ['nullable', 'string', 'max:180'],
            'business_lines' => ['array'],
            'business_lines.*' => ['exists:bid_business_lines,id'],
            'responsible_name' => ['nullable', 'string', 'max:180'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
            'zipcode' => ['nullable', 'string', 'max:9'],
            'address' => ['nullable', 'string', 'max:180'],
            'number' => ['nullable', 'string', 'max:20'],
            'complement' => ['nullable', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'size:2'],
            'color' => ['nullable', 'string', 'max:7'],
            'notes' => ['nullable', 'string'],
            'active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cnpj' => Br::digits($this->cnpj),
            'capital_social' => Br::money($this->input('capital_social')),
            'net_worth' => Br::money($this->input('net_worth')),
            'state' => $this->state ? strtoupper($this->state) : null,
            'active' => $this->boolean('active'),
            'cnaes' => $this->normalizedCnaes(),
        ]);
    }

    /** Descarta linhas de CNAE vazias e marca a principal (primeira, se nenhuma marcada). */
    protected function normalizedCnaes(): array
    {
        $rows = collect($this->input('cnaes', []))
            ->filter(fn ($cnae) => filled($cnae['code'] ?? null))
            ->map(fn ($cnae) => [
                'code' => Br::digits($cnae['code']),
                'description' => $cnae['description'] ?? null,
                'primary' => (bool) ($cnae['primary'] ?? false),
            ])
            ->values();

        if ($rows->isNotEmpty() && ! $rows->contains(fn ($cnae) => $cnae['primary'])) {
            $rows = $rows->map(fn ($cnae, $i) => $i === 0 ? ['primary' => true] + $cnae : $cnae);
        }

        return $rows->all();
    }

    public function attributes(): array
    {
        return [
            'corporate_name' => 'razão social',
            'trade_name' => 'nome fantasia',
            'cnpj' => 'CNPJ',
            'size' => 'porte',
            'capital_social' => 'capital social',
            'net_worth' => 'patrimônio líquido',
        ];
    }

    public function messages(): array
    {
        return ['cnpj.unique' => 'Já existe uma empresa licitante com este CNPJ.'];
    }
}
