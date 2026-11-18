<?php

namespace App\Http\Requests;

class UpdateBankAccountRequest extends StoreBankAccountRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('bankAccount'));
    }

    protected function ignoreId(): ?int
    {
        return $this->route('bankAccount')->id;
    }
}
