<?php

namespace Tests\Feature\FixedCosts;

use App\Actions\Finance\SyncCardToFinance;
use App\Domain\Enums\UserRole;
use App\Models\Board;
use App\Models\Card;
use App\Models\Event;
use Illuminate\Validation\ValidationException;

/** Quadro de custos fixos (specs/27 §1 e §4). */
class FixedCostBoardTest extends FixedCostTestCase
{
    private function manualCard(Board $board, array $attributes = []): Card
    {
        return Card::create($attributes + [
            'board_id' => $board->id,
            'board_column_id' => $board->columns()->orderBy('position')->first()->id,
            'title' => 'Conta avulsa',
        ]);
    }

    public function test_chave_do_quadro_e_exclusiva_com_o_financeiro_e_trava_com_custos_apontando(): void
    {
        $admin = $this->user();
        $finance = Board::create(['name' => 'Financeiro', 'feeds_finance' => true, 'active' => true]);
        $board = $this->board('Contas', false);

        $this->actingAs($admin)->putJson(route('boards.fixed-costs.update', $finance), ['fixed_costs' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fixed_costs' => 'Um quadro de custos fixos não pode alimentar o Financeiro.']);

        $this->actingAs($admin)->putJson(route('boards.fixed-costs.update', $board), ['fixed_costs' => true])
            ->assertOk()->assertJson(['fixed_costs' => true]);
        $this->assertTrue($board->fresh()->fixed_costs);

        $this->actingAs($admin)->get(route('boards.config', $board))->assertOk()
            ->assertSee('Este quadro recebe custos fixos')
            ->assertSee('Quadros de custos fixos são visíveis só para Admin e Coordenador sem eventos vinculados.');

        $this->makeCost($board->fresh(), $this->empresa(), ['installments_total' => 1]);

        $this->actingAs($admin)->putJson(route('boards.fixed-costs.update', $board), ['fixed_costs' => false])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fixed_costs' => '1 custo(s) fixo(s) geram cards neste quadro. Troque o quadro de destino deles antes.']);
    }

    public function test_quadro_visivel_so_para_admin_e_coordenador_sem_eventos(): void
    {
        $board = $this->board();
        $card = $this->makeCost($board, $this->empresa(), ['installments_total' => 1])->installments()->first()->card;

        $usuario = $this->user(UserRole::Usuario);
        $usuario->boards()->attach($board->id);
        // Nem como responsável o usuário comum entra.
        $card->update(['assignee_id' => $usuario->id]);

        $this->actingAs($usuario)->get(route('boards.show', $board))->assertForbidden();
        $this->actingAs($usuario)->getJson(route('cards.show', $card))->assertForbidden();
        $this->actingAs($usuario)->get(route('boards.index'))->assertOk()->assertDontSee('Custos Fixos');

        $this->actingAs($this->scopedCoordinator())->get(route('boards.show', $board))->assertForbidden();

        $this->actingAs($this->user(UserRole::Coordenador))->get(route('boards.show', $board))->assertOk();
        $this->actingAs($this->user())->get(route('boards.index'))->assertOk()->assertSee('Custos Fixos');
    }

    public function test_transferencia_e_reabertura_entre_tipos_diferentes_sao_recusadas(): void
    {
        $admin = $this->user();
        $fixed = $this->board();
        $events = $this->board('Orçamentos', false);
        $fixedCard = $this->manualCard($fixed, ['empresa_id' => $this->empresa()->id]);
        $eventCard = $this->manualCard($events);

        $this->actingAs($admin)->postJson(route('cards.transfer', $fixedCard), ['board_id' => $events->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['board_id' => 'Cards de custos fixos não podem ir para quadros de eventos.']);

        $this->actingAs($admin)->postJson(route('cards.transfer', $eventCard), ['board_id' => $fixed->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['board_id' => 'Cards de eventos não podem ir para quadros de custos fixos.']);

        $eventCard->update(['concluded_at' => now()]);
        $this->actingAs($admin)->postJson(route('cards.reopen', $eventCard), ['board_id' => $fixed->id])
            ->assertUnprocessable();

        // Entre quadros do mesmo tipo continua livre.
        $otherFixed = $this->board('Custos Fixos 2');
        $this->actingAs($admin)->postJson(route('cards.transfer', $fixedCard), ['board_id' => $otherFixed->id])->assertOk();
        $this->assertSame($otherFixed->id, $fixedCard->fresh()->board_id);
    }

    public function test_card_no_quadro_de_custos_fixos_exige_empresa_e_nao_aceita_evento(): void
    {
        $admin = $this->user();
        $board = $this->board();
        $event = Event::create(['name' => 'Show', 'location' => 'GO', 'start_date' => '2026-06-20', 'end_date' => '2026-06-22', 'active' => true]);

        $this->actingAs($admin)->postJson(route('cards.store', $board), ['title' => 'Internet', 'event_id' => $event->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['empresa_id', 'event_id']);

        $this->actingAs($admin)->postJson(route('cards.store', $board), [
            'title' => 'Internet', 'empresa_id' => $this->empresa()->id, 'event_id' => '', 'assignee_id' => $this->user(UserRole::Usuario)->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['assignee_id']);

        $this->actingAs($admin)->postJson(route('cards.store', $board), [
            'title' => 'Internet', 'empresa_id' => $this->empresa('Outra', '11444777000161')->id, 'event_id' => '',
        ])->assertCreated()->assertJson(['installment' => null]);
    }

    public function test_cards_de_custos_fixos_nao_vao_para_o_financeiro(): void
    {
        $board = $this->board();
        $card = $this->manualCard($board, ['empresa_id' => $this->empresa()->id]);

        $this->expectException(ValidationException::class);
        app(SyncCardToFinance::class)->execute($card);
    }

    public function test_filtro_tipo_separa_parcelas_de_avulsos_e_selo_vem_no_kanban(): void
    {
        $admin = $this->user();
        $board = $this->board();
        $empresa = $this->empresa();
        $this->makeCost($board, $empresa, ['installments_total' => 2]);
        $manual = $this->manualCard($board, ['empresa_id' => $empresa->id]);

        $cards = fn (array $query) => collect(
            $this->actingAs($admin)->getJson(route('boards.kanban.data', ['board' => $board] + $query))->assertOk()->json('columns')
        )->flatMap(fn ($c) => $c['cards']);

        $this->assertCount(3, $cards([]));
        $this->assertSame([$manual->id], $cards(['kind' => 'manual'])->pluck('id')->all());

        $installments = $cards(['kind' => 'installments']);
        $this->assertCount(2, $installments);
        $this->assertSame(
            [['number' => 1, 'total' => 2], ['number' => 2, 'total' => 2]],
            $installments->map(fn ($c) => ['number' => $c['installment']['number'], 'total' => $c['installment']['total']])->sortBy('number')->values()->all()
        );

        // Painel do card: selo, tipo do quadro e atalho para o cadastro.
        $first = Card::whereNotNull('fixed_cost_installment_id')->orderBy('due_date')->first();
        $this->actingAs($admin)->getJson(route('cards.show', $first))->assertOk()
            ->assertJson(['board_fixed_costs' => true, 'installment' => ['number' => 1, 'total' => 2]])
            ->assertJsonPath('fixed_cost_url', route('fixed-costs.show', $first->fixedCostInstallment->fixed_cost_id));
    }
}
