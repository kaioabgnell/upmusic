<?php

namespace App\Http\Requests\Finance;

/** Mesmas regras da criação — a grade envia a linha inteira a cada autosave. */
class UpdateCostItemRequest extends StoreCostItemRequest
{
    protected function currentCostCenterId(): ?int
    {
        return $this->route('item')?->cost_center_id;
    }
}
