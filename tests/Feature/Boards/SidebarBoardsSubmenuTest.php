<?php

namespace Tests\Feature\Boards;

use App\Domain\Enums\UserRole;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Submenu de Quadros na sidebar: cada quadro vira um link direto.
 *
 * A régua de quem aparece é a mesma do `BoardController::index` (admin/coordenador veem todos,
 * usuário comum só os vinculados em `user_board`) — e não a da `BoardPolicy::view`, que é mais
 * larga de propósito: ser responsável por um card dá leitura do quadro, mas não o coloca no menu.
 */
class SidebarBoardsSubmenuTest extends TestCase
{
    use RefreshDatabase;

    private function board(string $name, int $position = 1): Board
    {
        $board = Board::create(['name' => $name, 'position' => $position]);
        BoardColumn::create(['board_id' => $board->id, 'name' => 'Entrada', 'position' => 1, 'is_entry' => true]);

        return $board;
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role->value, 'active' => true])->fresh();
    }

    public function test_admin_ve_todos_os_quadros_como_submenu(): void
    {
        $orcamentos = $this->board('Orçamentos');
        $producao = $this->board('Produção', 2);

        $html = $this->actingAs($this->user(UserRole::Admin))
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        foreach ([$orcamentos, $producao] as $board) {
            $this->assertStringContainsString(route('boards.show', $board), $html);
            $this->assertStringContainsString($board->name, $html);
        }
    }

    public function test_usuario_comum_so_ve_os_quadros_a_que_tem_acesso(): void
    {
        $meu = $this->board('Orçamentos');
        $alheio = $this->board('Financeiro', 2);

        $user = $this->user(UserRole::Usuario);
        $user->boards()->attach($meu);

        $html = $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('boards.show', $meu), $html);
        $this->assertStringNotContainsString(route('boards.show', $alheio), $html);
    }

    public function test_quadro_aberto_fica_marcado_no_submenu_e_a_lista_nao(): void
    {
        $board = $this->board('Orçamentos');
        $outro = $this->board('Produção', 2);

        $html = $this->actingAs($this->user(UserRole::Admin))
            ->get(route('boards.show', $board))
            ->assertOk()
            ->getContent();

        // O item ativo ganha o fundo laranja da marca; só um quadro pode estar aceso por vez, e o
        // item "Quadros / Processos" (a lista) não acende junto — por isso o `except`.
        $this->assertSame(1, substr_count($html, 'bg-brand-orange text-brand-ink font-semibold'));
        $this->assertMatchesRegularExpression(
            '/href="'.preg_quote(route('boards.show', $board), '/').'"[^>]*class="[^"]*bg-brand-orange text-brand-ink font-semibold/',
            $html,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/href="'.preg_quote(route('boards.show', $outro), '/').'"[^>]*class="[^"]*bg-brand-orange/',
            $html,
        );
    }
}
