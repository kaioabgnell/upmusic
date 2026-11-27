<?php

namespace App\Http\Requests;

class UpdateAccessProfileRequest extends AccessProfileRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('accessProfile'));
    }
}
