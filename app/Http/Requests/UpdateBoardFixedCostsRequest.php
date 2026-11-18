<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/** Chave "Quadro de custos fixos" na configuração do quadro (specs/27 §1.1). */
class UpdateBoardFixedCostsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('configure', $this->route('board'));
    }

    public function rules(): array
    {
        return ['fixed_costs' => ['required', 'boolean']];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $board = $this->route('board');

            if ($this->boolean('fixed_costs') && $board->feeds_finance) {
                $v->errors()->add('fixed_costs', 'Um quadro de custos fixos não pode alimentar o Financeiro.');
            }

            if (! $this->boolean('fixed_costs') && $board->fixed_costs) {
                $count = $board->fixedCostItems()->count();
                if ($count > 0) {
                    $v->errors()->add('fixed_costs', "{$count} custo(s) fixo(s) geram cards neste quadro. Troque o quadro de destino deles antes.");
                }
            }
        });
    }
}
