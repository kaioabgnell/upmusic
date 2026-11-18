<?php

namespace App\Http\Requests;

class UpdateCostCenterRequest extends StoreCostCenterRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('costCenter'));
    }

    protected function ignoreId(): ?int
    {
        return $this->route('costCenter')->id;
    }
}
