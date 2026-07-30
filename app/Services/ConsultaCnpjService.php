<?php

namespace App\Services;

use App\Support\Br;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Consulta de CNPJ (specs/19) — preenchimento automático da razão social nos cadastros rápidos
 * de empresa/fornecedor a partir do CNPJ digitado.
 *
 * Usa a BrasilAPI (espelha os dados públicos da Receita Federal, sem autenticação): a API oficial
 * do Conecta gov.br exige credenciamento como órgão público, o que não se aplica à Up Music.
 */
class ConsultaCnpjService
{
    public function find(string $cnpj): array
    {
        $digits = Br::digits($cnpj);

        if (! Br::isValidCnpj($digits)) {
            throw new ConsultaCnpjException('CNPJ inválido.', 422);
        }

        try {
            $response = Http::timeout((int) config('services.cnpj_lookup.timeout', 8))
                ->get(rtrim((string) config('services.cnpj_lookup.base_url'), '/')."/{$digits}");
        } catch (ConnectionException $e) {
            throw new ConsultaCnpjException('Falha de conexão ao consultar o CNPJ. Tente novamente.', 503);
        }

        if ($response->status() === 404) {
            throw new ConsultaCnpjException('CNPJ não encontrado.', 404);
        }

        if ($response->failed()) {
            throw new ConsultaCnpjException('Não foi possível consultar o CNPJ agora. Tente novamente.', 502);
        }

        $data = $response->json() ?? [];

        return [
            'razao_social' => $data['razao_social'] ?? null,
            'nome_fantasia' => $data['nome_fantasia'] ?? null,
        ];
    }
}
