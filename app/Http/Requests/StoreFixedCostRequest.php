<?php

namespace App\Http\Requests;

use App\Domain\Enums\FixedCostGenerationMode;
use App\Models\FixedCost;
use App\Support\Br;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Cadastro de custo fixo (specs/27 §2.1). */
class StoreFixedCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', FixedCost::class);
    }

    /** Custo em edição (null na criação): libera a empresa e o quadro que ele já usa, mesmo inativos. */
    protected function current(): ?FixedCost
    {
        return null;
    }

    public function rules(): array
    {
        $current = $this->current();

        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'empresa_id' => ['required', 'integer', Rule::exists('empresas', 'id')->where(fn (Builder $q) => $q
                ->whereNull('deleted_at')
                ->where(fn (Builder $w) => $w->where('active', true)->orWhere('id', $current?->empresa_id)))],
            'installment_value' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99'],
            'installments_total' => ['required', 'integer', 'min:1', 'max:360'],
            'start_installment' => ['required', 'integer', 'min:1', 'lte:installments_total'],
            'first_due_date' => ['required', 'date'],
            'board_id' => ['required', 'integer', Rule::exists('boards', 'id')->where(fn (Builder $q) => $q
                ->whereNull('deleted_at')
                ->where('fixed_costs', true)
                ->where(fn (Builder $w) => $w->where('active', true)->orWhere('id', $current?->board_id)))],
            'board_column_id' => ['nullable', 'integer',
                Rule::exists('board_columns', 'id')->where('board_id', (int) $this->input('board_id'))],
            'generation_mode' => ['required', Rule::enum(FixedCostGenerationMode::class)],
            'lead_days' => ['nullable', 'required_if:generation_mode,'.FixedCostGenerationMode::LeadDays->value,
                'integer', 'min:0', 'max:60'],
            'active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $mode = $this->input('generation_mode');

        $this->merge([
            'installment_value' => Br::money($this->input('installment_value')),
            'start_installment' => filled($this->input('start_installment')) ? $this->input('start_installment') : 1,
            'board_column_id' => $this->input('board_column_id') ?: null,
            // Dias de antecedência só existem no modo "X dias antes" — no outro modo, a chave é limpa.
            'lead_days' => $mode === FixedCostGenerationMode::LeadDays->value ? $this->input('lead_days') : null,
            'active' => $this->has('active') ? $this->boolean('active') : true,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $min = $this->current()?->maxGeneratedNumber() ?? 0;

            if ($min > 0 && (int) $this->input('installments_total') < $min) {
                $v->errors()->add('installments_total', "Já existem parcelas geradas até a {$min}. A quantidade mínima é {$min}.");
            }
        });
    }

    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'description' => 'descrição',
            'empresa_id' => 'empresa',
            'installment_value' => 'valor da parcela',
            'installments_total' => 'quantidade de parcelas',
            'start_installment' => 'começar na parcela',
            'first_due_date' => 'primeiro vencimento',
            'board_id' => 'quadro de destino',
            'board_column_id' => 'coluna de destino',
            'generation_mode' => 'modo de geração',
            'lead_days' => 'dias de antecedência',
        ];
    }

    public function messages(): array
    {
        return [
            'board_id.exists' => 'Escolha um quadro de custos fixos ativo.',
            'empresa_id.exists' => 'Escolha uma empresa ativa.',
            'start_installment.lte' => 'A parcela inicial não pode passar da quantidade de parcelas.',
            'lead_days.required_if' => 'Informe quantos dias antes do vencimento o card deve ser criado.',
        ];
    }
}
