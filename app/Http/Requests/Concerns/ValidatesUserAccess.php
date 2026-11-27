<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

/** Campos de acesso do cadastro de usuário (specs/28 §7.2), comuns a criar e editar. */
trait ValidatesUserAccess
{
    protected function accessRules(): array
    {
        $notAdmin = fn () => ! $this->boolean('is_admin');

        return [
            'is_admin' => ['boolean'],
            'access_profile_id' => [Rule::requiredIf($notAdmin), 'nullable', 'integer', 'exists:access_profiles,id'],
            'event_scope' => ['in:all,selected'],
            'events' => [Rule::requiredIf(fn () => $notAdmin() && $this->input('event_scope') === 'selected'), 'array'],
            'events.*' => ['integer', 'exists:events,id'],
        ];
    }

    protected function prepareAccessInput(): void
    {
        $this->merge([
            'active' => $this->boolean('active'),
            'is_admin' => $this->boolean('is_admin'),
            'event_scope' => $this->input('event_scope') === 'selected' ? 'selected' : 'all',
        ]);
    }

    public function messages(): array
    {
        return [
            'access_profile_id.required' => 'Escolha um perfil de acesso ou marque Administrador.',
            'events.required' => 'Selecione ao menos um evento ou escolha "Todos os eventos".',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nome', 'email' => 'e-mail', 'access_profile_id' => 'perfil de acesso',
            'setor_id' => 'setor', 'phone' => 'telefone', 'password' => 'senha', 'events' => 'eventos',
        ];
    }
}
