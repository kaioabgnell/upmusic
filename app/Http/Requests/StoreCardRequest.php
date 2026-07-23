<?php

namespace App\Http\Requests;

use App\Models\Card;
use App\Support\Br;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Card::class, $this->route('board')]);
    }

    public function rules(): array
    {
        $board = $this->route('board');

        return [
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'empresa_id' => ['nullable', 'exists:empresas,id'],
            'fornecedor_id' => ['nullable', 'exists:fornecedores,id'],
            'event_id' => $this->eventRule(),
            'assignee_id' => ['nullable', 'exists:users,id'],
            'board_column_id' => ['nullable', Rule::exists('board_columns', 'id')->where('board_id', $board->id)],
            'estimated_value' => ['nullable', 'numeric'],
            'actual_value' => ['nullable', 'numeric'],
            'due_date' => ['nullable', 'date'],
            'priority' => ['nullable', Rule::in(['baixa', 'media', 'alta'])],
            'fields' => ['array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'estimated_value' => Br::money($this->input('estimated_value')),
            'actual_value' => Br::money($this->input('actual_value')),
        ]);
    }

    /**
     * Regra do evento (specs/20): para o coordenador restrito, o evento é obrigatório e precisa estar
     * dentro dos eventos vinculados (o select já vem filtrado, isto impede burlar via requisição). Para
     * os demais perfis, evento continua opcional e livre.
     */
    private function eventRule(): array
    {
        $ids = $this->user()->allowedEventIds();

        if ($ids === null) {
            return ['nullable', 'exists:events,id'];
        }

        return ['required', Rule::in($ids->all())];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $board = $this->route('board');
            $fields = (array) $this->input('fields', []);

            foreach ($board->fields()->where('required', true)->get() as $field) {
                $value = $fields[$field->id] ?? null;
                if ($value === null || $value === '' || $value === []) {
                    $v->errors()->add("fields.{$field->id}", "O campo \"{$field->label}\" é obrigatório.");
                }
            }
        });
    }

    public function attributes(): array
    {
        return ['title' => 'título', 'empresa_id' => 'empresa', 'fornecedor_id' => 'fornecedor', 'event_id' => 'evento', 'assignee_id' => 'responsável', 'due_date' => 'prazo'];
    }
}
