<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuickUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'arquivos' => ['required', 'array', 'min:1'],
            'arquivos.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
        ];
    }

    public function attributes(): array
    {
        return ['arquivos' => 'arquivos', 'arquivos.*' => 'arquivo'];
    }
}
