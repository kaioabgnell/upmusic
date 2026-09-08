<?php

namespace Tests\Feature\Fornecedores;

use App\Domain\Enums\UserRole;
use App\Models\Fornecedor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Dados bancários do fornecedor (Banco/Agência/Conta/PIX) — todos opcionais. */
class FornecedorDadosBancariosTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['role' => UserRole::Usuario->value, 'active' => true])->fresh();
    }

    public function test_cadastra_fornecedor_com_dados_bancarios(): void
    {
        $this->actingAs($this->user())
            ->post(route('fornecedores.store'), [
                'type' => 'PJ',
                'name' => 'Fulano de Tal',
                'document' => '123.456.789-09',
                'bank_name' => 'Banco do Brasil',
                'bank_agency' => '1234',
                'bank_account' => '56789-0',
                'pix_key' => 'fulano@example.com',
                'active' => '1',
            ])
            ->assertRedirect(route('fornecedores.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('fornecedores', [
            'name' => 'Fulano de Tal',
            'bank_name' => 'Banco do Brasil',
            'bank_agency' => '1234',
            'bank_account' => '56789-0',
            'pix_key' => 'fulano@example.com',
        ]);
    }

    public function test_dados_bancarios_sao_opcionais(): void
    {
        $this->actingAs($this->user())
            ->post(route('fornecedores.store'), [
                'type' => 'PJ',
                'name' => 'Sem Dados Bancarios',
                'document' => '11.444.777/0001-61',
                'active' => '1',
            ])
            ->assertRedirect(route('fornecedores.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('fornecedores', [
            'name' => 'Sem Dados Bancarios',
            'bank_name' => null,
            'pix_key' => null,
        ]);
    }

    public function test_atualiza_dados_bancarios_de_fornecedor_existente(): void
    {
        $fornecedor = Fornecedor::create([
            'type' => 'PJ', 'name' => 'Existente', 'document' => '12345678909', 'active' => true,
        ]);

        $this->actingAs($this->user())
            ->put(route('fornecedores.update', $fornecedor), [
                'type' => 'PJ',
                'name' => 'Existente',
                'document' => '123.456.789-09',
                'bank_name' => 'Nubank',
                'pix_key' => '12345678909',
                'active' => '1',
            ])
            ->assertRedirect(route('fornecedores.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('fornecedores', [
            'id' => $fornecedor->id,
            'bank_name' => 'Nubank',
            'pix_key' => '12345678909',
        ]);
    }
}
