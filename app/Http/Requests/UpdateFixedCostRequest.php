<?php

namespace App\Http\Requests;

use App\Models\FixedCost;

/** Edição do custo fixo (specs/27 §5.1). Mesmas regras do cadastro, com as travas pós-geração. */
class UpdateFixedCostRequest extends StoreFixedCostRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('fixedCost'));
    }

    protected function current(): ?FixedCost
    {
        return $this->route('fixedCost');
    }

    public function rules(): array
    {
        return parent::rules() + ['apply_to_generated' => ['boolean']];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        // Travados depois da 1ª parcela gerada: os campos chegam desabilitados (e por isso ausentes)
        // do formulário, e qualquer valor enviado à mão é trocado pelo que está gravado.
        $cost = $this->current();
        if ($cost->hasGeneratedInstallments()) {
            $this->merge([
                'start_installment' => $cost->start_installment,
                'first_due_date' => $cost->first_due_date->toDateString(),
            ]);
        }

        $this->merge(['apply_to_generated' => $this->boolean('apply_to_generated')]);
    }
}
