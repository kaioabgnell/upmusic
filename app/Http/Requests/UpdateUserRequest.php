<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesUserAccess;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    use ValidatesUserAccess;

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
            'setor_id' => ['nullable', 'exists:setores,id'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'active' => ['boolean'],
            ...$this->accessRules(),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->prepareAccessInput();
    }

    /**
     * Ninguém tira o próprio Administrador, e o último admin ativo não perde o Administrador
     * (specs/28 §7.2 e §10) — senão o sistema fica sem quem gerencie usuários e perfis.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $target = $this->route('user');

            if (! $target->isAdmin() || $this->boolean('is_admin')) {
                return;
            }

            if ($target->is($this->user())) {
                $v->errors()->add('is_admin', 'Você não pode tirar o seu próprio acesso de Administrador.');

                return;
            }

            $otherAdmins = User::where('is_admin', true)->where('active', true)->whereKeyNot($target->id)->exists();
            if (! $otherAdmins) {
                $v->errors()->add('is_admin', 'Não é possível tirar o Administrador do último administrador ativo.');
            }
        });
    }
}
