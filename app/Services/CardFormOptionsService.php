<?php

namespace App\Services;

use App\Domain\Enums\PessoaTipo;
use App\Models\Empresa;
use App\Models\Event;
use App\Models\Fornecedor;
use App\Models\User;
use App\Support\Br;

/**
 * Opções globais (não específicas de um quadro) usadas pelo formulário de card — reaproveitadas
 * tanto pelo Kanban de um quadro quanto pela listagem "Todos os cards", que compartilham o mesmo
 * modal de card (ver `boards/partials/card-panel.blade.php`).
 */
class CardFormOptionsService
{
    /** @return array{empresas: \Illuminate\Support\Collection, fornecedores: \Illuminate\Support\Collection, events: \Illuminate\Support\Collection, assignees: \Illuminate\Support\Collection} */
    public function globalOptions(): array
    {
        return [
            'empresas' => Empresa::active()->orderBy('corporate_name')->get(['id', 'corporate_name']),
            'fornecedores' => Fornecedor::active()->with('categoria:id,preco_interno')->orderBy('name')
                ->get(['id', 'name', 'type', 'document', 'phone', 'email', 'fornecedor_categoria_id'])
                ->map(fn ($f) => [
                    'id' => $f->id,
                    'name' => $f->name,
                    'document' => $f->type === PessoaTipo::PF ? Br::formatCpf($f->document) : Br::formatCnpj($f->document),
                    // Telefone/e-mail: exibidos no card quando o quadro permite solicitar minuta ao
                    // fornecedor (specs/19), para contato rápido sem precisar abrir o cadastro.
                    'phone' => $f->phone,
                    'email' => $f->email,
                    // Usado no modal de card para avisar se o "Valor previsto" ultrapassa o Preço
                    // Interno cadastrado na categoria do fornecedor (ver card-panel.js).
                    'preco_interno' => $f->categoria?->preco_interno !== null ? (float) $f->categoria->preco_interno : null,
                ])
                ->values(),
            'events' => Event::active()->orderByDesc('start_date')->get(['id', 'name']),
            'assignees' => User::where('active', true)->orderBy('name')->get(['id', 'name', 'avatar_path'])
                ->map(fn ($u) => (object) ['id' => $u->id, 'name' => $u->name, 'avatar_url' => $u->avatar_url])
                ->values(),
        ];
    }
}
