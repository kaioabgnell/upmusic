<?php

namespace App\Http\Requests;

use App\Domain\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'role' => ['required', Rule::in(array_column(UserRole::cases(), 'value'))],
            'setor_id' => ['nullable', 'exists:setores,id'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'active' => ['boolean'],
            'boards' => ['array'],
            'boards.*' => ['integer', 'exists:boards,id'],
            'events' => ['array'],
            'events.*' => ['integer', 'exists:events,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Coordenador só cria usuários comuns.
        if ($this->user()->isCoordenador()) {
            $this->merge(['role' => UserRole::Usuario->value]);
        }

        $this->merge(['active' => $this->boolean('active')]);
    }

    public function attributes(): array
    {
        return [
            'name' => 'nome', 'email' => 'e-mail', 'role' => 'perfil',
            'setor_id' => 'setor', 'phone' => 'telefone', 'password' => 'senha',
        ];
    }
}
