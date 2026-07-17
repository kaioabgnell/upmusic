<?php

namespace App\Http\Requests;

use App\Domain\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    public function rules(): array
    {
        $userId = $this->route('user')->id;

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($userId)],
            'role' => ['required', Rule::in(array_column(UserRole::cases(), 'value'))],
            'setor_id' => ['nullable', 'exists:setores,id'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'active' => ['boolean'],
            'boards' => ['array'],
            'boards.*' => ['integer', 'exists:boards,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
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
