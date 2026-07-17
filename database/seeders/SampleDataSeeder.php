<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\Fornecedor;
use App\Models\Service;
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
            ['type' => 'PJ', 'name' => 'Limpa Tudo Serviços Ltda', 'document' => '04.252.011/0001-10', 'category' => 'Limpeza'],
            ['type' => 'PJ', 'name' => 'Segura Bem Segurança Ltda', 'document' => '33.000.167/0001-01', 'category' => 'Segurança'],
            ['type' => 'PF', 'name' => 'Carlos Sonorização', 'document' => '529.982.247-25', 'category' => 'Som'],
        ];
        foreach ($fornecedores as $f) {
            // Armazena apenas dígitos, como no cadastro validado.
            $f['document'] = \App\Support\Br::digits($f['document']);
            Fornecedor::firstOrCreate(['document' => $f['document']], $f);
        }

        $services = [
            ['name' => 'Limpeza de evento', 'category' => 'Limpeza', 'unit' => 'diária'],
            ['name' => 'Segurança de evento', 'category' => 'Segurança', 'unit' => 'diária'],
            ['name' => 'Locação de som', 'category' => 'Som', 'unit' => 'diária'],
        ];
        foreach ($services as $s) {
            Service::firstOrCreate(['name' => $s['name']], $s);
        }
    }
}
