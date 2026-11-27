<?php

namespace App\Http\Requests;

use App\Domain\Enums\AccessModule;
use App\Models\AccessProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Campos comuns do cadastro de Perfil de acesso (specs/28 §7.1). */
abstract class AccessProfileRequest extends FormRequest
{
    public function rules(): array
    {
        $unique = Rule::unique('access_profiles', 'name');
        if ($profile = $this->route('accessProfile')) {
            $unique->ignore($profile->id);
        }

        return [
            'name' => ['required', 'string', 'max:'.AccessProfile::NAME_MAX, $unique],
            'modules' => ['array'],
            'modules.*' => ['string', 'distinct', Rule::in(AccessModule::values())],
            'boards' => ['array'],
            // Quadro de custos fixos não entra no perfil: ele vem com o módulo Custos fixos (§4).
            'boards.*' => ['integer', 'distinct', Rule::exists('boards', 'id')->where('fixed_costs', false)->whereNull('deleted_at')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Já existe um perfil de acesso com este nome.',
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nome', 'modules' => 'módulos', 'boards' => 'quadros'];
    }
}
