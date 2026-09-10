<?php

namespace Tests\Feature\Fornecedores;

use App\Domain\Enums\UserRole;
use App\Models\FornecedorCategoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A categoria cadastrada "no ato" (botão "Nova categoria" do próprio formulário de fornecedor)
 * precisa aparecer na mesma ordem alfabética da lista que veio do servidor — um push simples no
 * array do Alpine jogaria a categoria nova pro fim, quebrando o padrão visual do select.
 */
class FornecedorCategoriaOrderingTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['role' => UserRole::Usuario->value, 'active' => true])->fresh();
    }

    public function test_categorias_chegam_em_ordem_alfabetica_no_formulario_de_criacao(): void
    {
        // A migration já semeia categorias padrão; limpo pra testar só as minhas, sem depender
        // da ordem/composição da seed.
        FornecedorCategoria::query()->delete();

        FornecedorCategoria::create(['nome' => 'Som', 'active' => true]);
        FornecedorCategoria::create(['nome' => 'Cenografia', 'active' => true]);
        FornecedorCategoria::create(['nome' => 'Limpeza', 'active' => true]);

        $nomes = $this->actingAs($this->user())
            ->get(route('fornecedores.create'))
            ->assertOk()
            ->viewData('categorias')
            ->pluck('nome')
            ->all();

        $this->assertSame(['Cenografia', 'Limpeza', 'Som'], $nomes);
    }

    /**
     * Guarda de regressão: a categoria criada pelo modal precisa ser reordenada no client, não só
     * empilhada no fim do array reativo do Alpine.
     */
    public function test_categoria_criada_pelo_modal_reordena_a_lista_no_client(): void
    {
        $blade = file_get_contents(resource_path('views/fornecedores/_form.blade.php'));

        $this->assertMatchesRegularExpression(
            '/this\.categorias\.push\([^)]*\);\s*this\.categorias\.sort\(/',
            $blade,
            'Após inserir a categoria nova no array reativo, a lista precisa ser reordenada alfabeticamente.',
        );
    }
}
