<?php

namespace App\Http\Requests;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Event::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'location' => ['nullable', 'string', 'max:180'],
            'responsible_name' => ['nullable', 'string', 'max:180'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['active' => $this->boolean('active')]);
    }

    public function attributes(): array
    {
        return [
            'name' => 'nome do evento',
            'location' => 'local do evento',
            'responsible_name' => 'responsável',
            'phone' => 'telefone',
            'email' => 'e-mail',
            'start_date' => 'data de início',
            'end_date' => 'data de fim',
        ];
    }
}
