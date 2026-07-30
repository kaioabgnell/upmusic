<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Consulta de CNPJ (specs/19) — preenchimento automático da razão social nos cadastros rápidos
 * de empresa/fornecedor. O tráfego para a BrasilAPI é sempre mockado (ver ConsultaCnpjService).
 */
class CnpjLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_razao_social_for_a_valid_cnpj(): void
    {
        Http::fake([
            'brasilapi.com.br/*' => Http::response([
                'razao_social' => 'KAIO GOMES ANDRADE SANTOS CONSULTORIA EM TECNOLOGIA DA INFORMACAO LTDA',
                'nome_fantasia' => 'KAIO GOMES',
            ]),
        ]);

        $response = $this
            ->actingAs(User::factory()->create(['active' => true]))
            ->getJson('/cnpj/45745694000124');

        $response->assertOk()->assertJson([
            'razao_social' => 'KAIO GOMES ANDRADE SANTOS CONSULTORIA EM TECNOLOGIA DA INFORMACAO LTDA',
        ]);
    }

    public function test_returns_422_for_an_invalid_cnpj(): void
    {
        $response = $this
            ->actingAs(User::factory()->create(['active' => true]))
            ->getJson('/cnpj/11111111111111');

        $response->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_returns_404_when_cnpj_is_not_found(): void
    {
        Http::fake([
            'brasilapi.com.br/*' => Http::response(['message' => 'CNPJ não encontrado'], 404),
        ]);

        $response = $this
            ->actingAs(User::factory()->create(['active' => true]))
            ->getJson('/cnpj/45745694000124');

        $response->assertStatus(404);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/cnpj/45745694000124');

        $response->assertUnauthorized();
    }
}
