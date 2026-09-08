<?php

namespace Tests\Feature\Cards;

use App\Domain\Enums\AttachmentKind;
use App\Domain\Enums\FinanceDocumentKind;
use App\Domain\Enums\UserRole;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Modal "Enviar para o Financeiro" no painel do card (specs/23 §6.3).
 *
 * As <option> do tipo de documento são renderizadas pelo servidor a partir do enum, e não por um
 * x-for sobre dados da API: com x-for o Alpine inicializa o <select x-model> antes de as opções
 * existirem, o navegador cai na primeira ("Orçamento") e a tela passa a divergir do estado. Este
 * teste trava esse contrato.
 */
class CardPanelFinanceModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_tipos_de_documento_sao_renderizados_pelo_servidor_no_modal(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value, 'active' => true]);
        $board = Board::create(['name' => 'Orçamentos']);
        BoardColumn::create(['board_id' => $board->id, 'name' => 'Entrada', 'position' => 1, 'is_entry' => true]);

        $html = $this->actingAs($admin->fresh())
            ->get(route('boards.show', $board))
            ->assertOk()
            ->getContent();

        foreach (FinanceDocumentKind::cases() as $kind) {
            $this->assertStringContainsString(
                '<option value="'.$kind->value.'">'.$kind->label().'</option>',
                $html,
                "A opção \"{$kind->label()}\" precisa vir do servidor no modal do Financeiro.",
            );
        }
    }

    /**
     * O select de Anexos do card (upload no próprio card, não o modal do Financeiro) também é
     * server-rendered a partir de `AttachmentKind::selectable()` — mesma fonte que
     * `CardController` usa para validar. "Recibo" precisa aparecer nos dois, sem lista duplicada.
     */
    public function test_recibo_aparece_no_select_de_anexos_do_card_e_no_modal_do_financeiro(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value, 'active' => true]);
        $board = Board::create(['name' => 'Orçamentos']);
        BoardColumn::create(['board_id' => $board->id, 'name' => 'Entrada', 'position' => 1, 'is_entry' => true]);

        $html = $this->actingAs($admin->fresh())
            ->get(route('boards.show', $board))
            ->assertOk()
            ->getContent();

        $this->assertContains(AttachmentKind::Recibo, AttachmentKind::selectable());

        // 2 ocorrências no HTML bruto: o select de Anexos do card (fora de qualquer x-for) e o
        // select por-anexo do modal do Financeiro — este último vive dentro de um <template
        // x-for="a in ...">, então o Blade server-renderiza o bloco uma única vez; é o Alpine,
        // no navegador, quem o clona por anexo em runtime.
        $this->assertSame(
            2,
            substr_count($html, '<option value="recibo">Recibo</option>'),
        );
    }

    public function test_modal_do_financeiro_nao_usa_x_if_no_corpo(): void
    {
        // Guarda de regressão da armadilha documentada no topo do card-panel: x-if recria o subtree
        // e o <select x-model> nasce sem <option>, perdendo a seleção de Evento e do tipo do anexo.
        $blade = file_get_contents(resource_path('views/boards/partials/card-panel.blade.php'));

        $this->assertStringNotContainsString('x-if="!finance.loading && finance.data"', $blade);
        $this->assertStringContainsString('x-show="!finance.loading && finance.data"', $blade);
    }
}
