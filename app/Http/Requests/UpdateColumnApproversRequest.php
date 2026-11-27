<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Aprovadores de uma coluna (specs/17, alterada pela specs/28 §5): qualquer usuário ativo com acesso
 * ao quadro, não só Administradores.
 */
class UpdateColumnApproversRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('configure', $this->route('column')->board);
    }

    public function rules(): array
    {
        return [
            'user_ids' => ['array'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $ids = collect($this->input('user_ids', []))->map(fn ($id) => (int) $id)->unique();
            if ($ids->isEmpty() || $v->errors()->isNotEmpty()) {
                return;
            }

            $column = $this->route('column');

            $allowed = User::where('active', true)
                ->withBoardAccess($column->board)
                ->whereIn('id', $ids)
                ->count();

            if ($allowed !== $ids->count()) {
                $v->errors()->add('user_ids', 'Só usuários ativos com acesso a este quadro podem ser aprovadores.');
            }

            if (! $column->nextColumn()) {
                $v->errors()->add('user_ids', 'Não é possível exigir aprovação na última etapa do quadro.');
            }
        });
    }

    public function attributes(): array
    {
        return ['user_ids' => 'aprovadores'];
    }
}
