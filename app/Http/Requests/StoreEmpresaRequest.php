<?php

namespace App\Http\Requests;

use App\Domain\Enums\PessoaTipo;
use App\Models\Empresa;
use App\Rules\Cnpj;
use App\Rules\Cpf;
use App\Support\Br;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmpresaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Empresa::class);
    }

    public function rules(): array
    {
        $documentRule = $this->input('type') === PessoaTipo::PF->value ? new Cpf : new Cnpj;

        return [
            'corporate_name' => ['required', 'string', 'max:180'],
            'trade_name' => ['nullable', 'string', 'max:180'],
            'type' => ['required', Rule::in(['PF', 'PJ'])],
            'document' => ['required', 'string', $documentRule, Rule::unique('empresas', 'document')->whereNull('deleted_at')],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
            'zipcode' => ['nullable', 'string', 'max:9'],
            'address' => ['nullable', 'string', 'max:180'],
            'number' => ['nullable', 'string', 'max:20'],
            'complement' => ['nullable', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'size:2'],
            'notes' => ['nullable', 'string'],
            'active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' => $this->input('type', PessoaTipo::PJ->value),
            'document' => Br::digits($this->document),
            'active' => $this->boolean('active'),
            'state' => $this->state ? strtoupper($this->state) : null,
        ]);
    }

    public function attributes(): array
    {
        return ['corporate_name' => 'razão social', 'trade_name' => 'nome fantasia', 'type' => 'tipo', 'document' => 'documento'];
    }
}
