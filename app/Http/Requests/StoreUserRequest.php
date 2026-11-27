<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesUserAccess;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    use ValidatesUserAccess;

    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'setor_id' => ['nullable', 'exists:setores,id'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'active' => ['boolean'],
            ...$this->accessRules(),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->prepareAccessInput();
    }
}
