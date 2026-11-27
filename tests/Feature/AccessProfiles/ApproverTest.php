<?php

namespace Tests\Feature\AccessProfiles;

/** Aprovador de etapa = usuário ativo com acesso ao quadro (specs/28 §5). */
class ApproverTest extends AccessProfileTestCase
{
    public function test_configuracao_lista_e_aceita_so_quem_acessa_o_quadro(): void
    {
        $board = $this->board();
        $entrada = $board->columns()->orderBy('position')->first();
        $gestor = $this->giveBoards($this->userAs('sem-acesso', ['name' => 'Gestora Comercial']), $board);
        $estranho = $this->userAs('sem-acesso', ['name' => 'Pessoa de Fora']);
        $admin = $this->userAs('admin');

        $this->actingAs($admin)->get(route('boards.config', $board))->assertOk()
            ->assertSee('Gestora Comercial')
            ->assertDontSee('Pessoa de Fora');

        $this->actingAs($admin)->putJson(route('columns.approvers.update', $entrada), ['user_ids' => [$estranho->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_ids' => 'Só usuários ativos com acesso a este quadro podem ser aprovadores.']);

        $this->actingAs($admin)->putJson(route('columns.approvers.update', $entrada), ['user_ids' => [$gestor->id]])
            ->assertOk();
        $this->assertSame([$gestor->id], $entrada->approvers()->pluck('users.id')->all());
    }

    public function test_ultima_etapa_nao_exige_aprovacao(): void
    {
        $board = $this->board();
        $ultima = $board->columns()->reorder('position', 'desc')->first();

        $this->actingAs($this->userAs('admin'))
            ->putJson(route('columns.approvers.update', $ultima), ['user_ids' => [$this->userAs('admin')->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_ids' => 'Não é possível exigir aprovação na última etapa do quadro.']);
    }

    public function test_aprovador_que_nao_e_admin_aprova_e_perde_o_poder_sem_o_quadro(): void
    {
        $board = $this->board();
        $entrada = $board->columns()->orderBy('position')->first();
        $gestor = $this->giveBoards($this->userAs('sem-acesso'), $board);
        $entrada->approvers()->attach($gestor->id);

        $card = $this->card($board);
        $this->actingAs($gestor)->postJson(route('cards.approve', $card))->assertOk();
        $this->assertNotSame($entrada->id, $card->fresh()->board_column_id);

        $outro = $this->card($board);
        $gestor->accessProfile->boards()->detach($board->id);

        $this->actingAs($gestor)->postJson(route('cards.approve', $outro))->assertForbidden();
    }
}
