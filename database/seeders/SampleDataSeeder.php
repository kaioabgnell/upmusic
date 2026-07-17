<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\Fornecedor;
use App\Models\FornecedorCategoria;
use Illuminate\Database\Seeder;

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $empresas = [
            ['corporate_name' => 'Produtora Alfa Eventos Ltda', 'trade_name' => 'Alfa Eventos', 'type' => 'PJ', 'document' => '11.444.777/0001-61', 'city' => 'São Paulo', 'state' => 'SP'],
            ['corporate_name' => 'Beta Entretenimento S.A.', 'trade_name' => 'Beta Shows', 'type' => 'PJ', 'document' => '11.222.333/0001-81', 'city' => 'Rio de Janeiro', 'state' => 'RJ'],
        ];
        foreach ($empresas as $e) {
            $e['document'] = \App\Support\Br::digits($e['document']);
            Empresa::firstOrCreate(['document' => $e['document']], $e);
        }

        $fornecedores = [
            ['type' => 'PJ', 'name' => 'Limpa Tudo Serviços Ltda', 'document' => '04.252.011/0001-10', 'categoria' => 'Limpeza'],
            ['type' => 'PJ', 'name' => 'Segura Bem Segurança Ltda', 'document' => '33.000.167/0001-01', 'categoria' => 'Segurança'],
            ['type' => 'PF', 'name' => 'Carlos Sonorização', 'document' => '529.982.247-25', 'categoria' => 'Som'],
        ];
        foreach ($fornecedores as $f) {
            $f['fornecedor_categoria_id'] = FornecedorCategoria::where('nome', $f['categoria'])->value('id');
            unset($f['categoria']);
            // Armazena apenas dígitos, como no cadastro validado.
            $f['document'] = \App\Support\Br::digits($f['document']);
            Fornecedor::firstOrCreate(['document' => $f['document']], $f);
        }
    }
}
