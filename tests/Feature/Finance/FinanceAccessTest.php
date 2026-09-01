<?php

namespace Tests\Feature\Finance;

use App\Domain\Enums\UserRole;

/** Permissões do módulo e o efeito do fechamento (specs/23 §11). */
class FinanceAccessTest extends FinanceTestCase
{
    public function test_usuario_comum_nao_acessa_o_modulo(): void
    {
        $this->actingAs($this->user(UserRole::Usuario))
            ->get(route('finance.index'))
            ->assertForbidden();
    }

    public function test_admin_e_coordenador_acessam_a_lista(): void
    {
        $this->actingAs($this->user(UserRole::Admin))->get(route('finance.index'))->assertOk();
        $this->actingAs($this->user(UserRole::Coordenador))->get(route('finance.index'))->assertOk();
    }

    public function test_coordenador_restrito_so_enxerga_a_planilha_dos_eventos_dele(): void
    {
        $meu = $this->event('Meu evento');
        $outro = $this->event('Evento de terceiros');

        $coordenador = $this->user(UserRole::Coordenador);
        $coordenador->events()->attach($meu->id);

        $this->actingAs($coordenador->fresh())->get(route('finance.show', $meu))->assertOk();
        $this->actingAs($coordenador->fresh())->get(route('finance.show', $outro))->assertForbidden();
    }

    public function test_lista_do_coordenador_restrito_traz_apenas_os_eventos_vinculados(): void
    {
        $meu = $this->event('Meu evento');
        $this->event('Evento de terceiros');

        $coordenador = $this->user(UserRole::Coordenador);
        $coordenador->events()->attach($meu->id);

        $this->actingAs($coordenador->fresh())
            ->get(route('finance.index'))
            ->assertOk()
            ->assertSee('Meu evento')
            ->assertDontSee('Evento de terceiros');
    }

    public function test_planilha_fechada_recusa_escrita(): void
    {
        $event = $this->event();
        $sheet = $this->sheet($event);
        $item = $sheet->costItems()->create(['description' => 'Palco'])->refresh();
        $sheet->update(['status' => 'fechado']);

        // 422 (e não 403) porque o motivo não é falta de permissão: a planilha está congelada.
        // Vale para qualquer papel — inclusive Admin, que o Gate::before liberaria numa policy.
        foreach ([UserRole::Coordenador, UserRole::Admin] as $role) {
            $this->actingAs($this->user($role))
                ->putJson(route('finance.costs.update', $item), ['description' => 'Alterado'])
                ->assertStatus(422);
        }

        $this->assertSame('Palco', $item->refresh()->description);
    }

    public function test_apenas_admin_reabre_prestacao_de_contas(): void
    {
        $event = $this->event();
        $sheet = $this->sheet($event);
        $sheet->update(['status' => 'fechado']);

        $this->actingAs($this->user(UserRole::Coordenador))
            ->post(route('finance.reopen', $event))
            ->assertForbidden();

        $this->actingAs($this->user(UserRole::Admin))
            ->post(route('finance.reopen', $event))
            ->assertRedirect();

        $this->assertFalse($sheet->refresh()->isClosed());
    }

    public function test_configuracoes_sao_exclusivas_do_admin(): void
    {
        $this->actingAs($this->user(UserRole::Coordenador))
            ->get(route('finance.settings.index'))
            ->assertForbidden();

        $this->actingAs($this->user(UserRole::Admin))
            ->get(route('finance.settings.index'))
            ->assertOk();
    }

    public function test_usuario_comum_pode_enviar_o_card_dele_para_o_financeiro(): void
    {
        $event = $this->event();
        $board = $this->board();
        $usuario = $this->user(UserRole::Usuario);
        $usuario->boards()->attach($board->id);
        $card = $this->card($board, $event);

        // Ele não abre o módulo, mas empurra a despesa a partir do card — a autorização é da
        // CardPolicy, não da FinanceSheetPolicy (specs/23 §11).
        $this->actingAs($usuario->fresh())
            ->postJson(route('cards.finance.sync', $card), ['event_id' => $event->id])
            ->assertCreated();

        $this->assertDatabaseHas('finance_cost_items', ['card_id' => $card->id]);
    }
}
