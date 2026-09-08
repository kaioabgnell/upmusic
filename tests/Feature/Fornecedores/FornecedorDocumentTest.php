<?php

namespace Tests\Feature\Fornecedores;

use App\Domain\Enums\UserRole;
use App\Models\Fornecedor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O documento aceita CPF ou CNPJ independente do "Tipo" (PF/PJ): há fornecedor cadastrado como
 * PJ cujo pagamento vai direto a uma pessoa física, e vice-versa — o Tipo classifica o fornecedor,
 * mas não trava o formato do documento (ver App\Rules\CpfOuCnpj).
 */
class FornecedorDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        // ->fresh(): sem isso o model só carrega os atributos passados ao factory, e
        // Model::preventAccessingMissingAttributes() (ligado fora de produção) explode ao
        // renderizar o layout completo (x-user-avatar lê avatar_path, ausente do array criado).
        return User::factory()->create(['role' => UserRole::Usuario->value, 'active' => true])->fresh();
    }

    public function test_fornecedor_pj_aceita_cpf_como_documento(): void
    {
        $this->actingAs($this->user())
            ->post(route('fornecedores.store'), [
                'type' => 'PJ',
                'name' => 'Fulano de Tal',
                'document' => '123.456.789-09',
                'active' => '1',
            ])
            ->assertRedirect(route('fornecedores.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('fornecedores', ['type' => 'PJ', 'document' => '12345678909']);
    }

    public function test_fornecedor_pf_aceita_cnpj_como_documento(): void
    {
        $this->actingAs($this->user())
            ->post(route('fornecedores.store'), [
                'type' => 'PF',
                'name' => 'Representante Comercial',
                'document' => '11.444.777/0001-61',
                'active' => '1',
            ])
            ->assertRedirect(route('fornecedores.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('fornecedores', ['type' => 'PF', 'document' => '11444777000161']);
    }

    public function test_documento_invalido_e_recusado_independente_do_tipo(): void
    {
        $this->actingAs($this->user())
            ->post(route('fornecedores.store'), [
                'type' => 'PJ',
                'name' => 'Qualquer',
                'document' => '111.111.111-11', // 11 dígitos, mas CPF inválido (dígitos verificadores)
                'active' => '1',
            ])
            ->assertSessionHasErrors('document');

        $this->assertDatabaseMissing('fornecedores', ['name' => 'Qualquer']);
    }

    public function test_quick_create_tambem_aceita_cpf_para_pj(): void
    {
        $response = $this->actingAs($this->user())
            ->postJson(route('fornecedores.quick'), [
                'type' => 'PJ',
                'name' => 'Fulano de Tal',
                'document' => '123.456.789-09',
            ])
            ->assertCreated();

        $this->assertSame('123.456.789-09', $response->json('document'));
        $this->assertDatabaseHas('fornecedores', ['type' => 'PJ', 'document' => '12345678909']);
    }

    public function test_listagem_formata_documento_pela_quantidade_de_digitos_nao_pelo_tipo(): void
    {
        Fornecedor::create([
            'type' => 'PJ', 'name' => 'Fulano de Tal', 'document' => '12345678909', 'active' => true,
        ]);

        $this->actingAs($this->user())
            ->get(route('fornecedores.index'))
            ->assertOk()
            ->assertSee('123.456.789-09');
    }
}
