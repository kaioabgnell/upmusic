<?php

namespace Database\Seeders;

use App\Models\Board;
use App\Models\Setor;
use App\Models\User;
use Illuminate\Database\Seeder;

class BoardSeeder extends Seeder
{
    public function run(): void
    {
        // Colunas padrão por setor (ver specs/12). Cada item: [nome, is_entry, is_final].
        $flows = [
            'Orçamentos' => [
                ['Solicitação de Compra/Contratação', true, false],
                ['Coleta de Orçamentos', false, false],
                ['Aprovação do Orçamento', false, true],
            ],
            'Jurídico' => [
                ['Confecção e Assinatura do Contrato', false, true],
            ],
            'Financeiro' => [
                ['Recebimento da Nota Fiscal', true, false],
                ['Liberação para Pagamento', false, false],
                ['Pagamento Realizado', false, true],
            ],
            'Conclusão' => [
                ['Prestação de Contas / Fotos de Comprovação', false, false],
                ['Finalizado', false, true],
            ],
        ];

        $position = 0;
        foreach ($flows as $setorNome => $columns) {
            $setor = Setor::where('nome', $setorNome)->first();
            if (! $setor) {
                continue;
            }

            $board = Board::firstOrCreate(
                ['setor_id' => $setor->id, 'name' => $setorNome],
                [
                    'description' => $setor->descricao,
                    'color' => $setor->color,
                    'icon' => $setor->icon,
                    'position' => $position++,
                    'active' => true,
                ]
            );

            if ($board->columns()->count() === 0) {
                foreach ($columns as $i => [$name, $isEntry, $isFinal]) {
                    $board->columns()->create([
                        'name' => $name,
                        'position' => $i,
                        'is_entry' => $isEntry,
                        'is_final' => $isFinal,
                    ]);
                }
            }
        }

        // Dá ao usuário de operação acesso ao quadro de Orçamentos.
        $usuario = User::where('email', 'usuario@upmusic.local')->first();
        $orcamentos = Board::where('name', 'Orçamentos')->first();
        if ($usuario && $orcamentos) {
            $usuario->boards()->syncWithoutDetaching([$orcamentos->id]);
        }
    }
}
