<?php

namespace Database\Seeders;

use App\Models\Setor;
use Illuminate\Database\Seeder;

class SetorSeeder extends Seeder
{
    public function run(): void
    {
        $setores = [
            ['nome' => 'Orçamentos', 'icon' => 'fa-file-invoice-dollar', 'color' => '#ff8c1e', 'descricao' => 'Solicitação, coleta e aprovação de orçamentos.'],
            ['nome' => 'Jurídico',   'icon' => 'fa-file-signature',       'color' => '#000000', 'descricao' => 'Confecção e assinatura de contratos.'],
            ['nome' => 'Financeiro', 'icon' => 'fa-money-bill-wave',      'color' => '#ff8c1e', 'descricao' => 'Notas fiscais, liberação e pagamento.'],
            ['nome' => 'Conclusão',  'icon' => 'fa-flag-checkered',       'color' => '#000000', 'descricao' => 'Prestação de contas e finalização.'],
        ];

        foreach ($setores as $data) {
            Setor::firstOrCreate(['nome' => $data['nome']], $data);
        }
    }
}
