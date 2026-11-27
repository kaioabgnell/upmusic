<?php

namespace Tests\Feature\FixedCosts;

use App\Domain\Enums\AccessModule;
use App\Domain\Enums\CardOrigin;
use App\Domain\Enums\FixedCostGenerationMode;
use App\Models\Card;
use App\Models\FixedCost;

/** Cadastro de custos fixos (specs/27 §2 e §5). */
class FixedCostCrudTest extends FixedCostTestCase
{
    public function test_cadastro_todas_agora_gera_um_card_por_parcela_com_vencimento_no_fim_do_mes(): void
    {
        $admin = $this->user();
        $board = $this->board();
        $empresa = $this->empresa();

        $this->actingAs($admin)
            ->post(route('fixed-costs.store'), $this->payload($board, $empresa))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $cost = FixedCost::firstOrFail();
        $this->assertSame(1500.0, (float) $cost->installment_value);
        $this->assertSame($admin->id, $cost->created_by);

        $cards = Card::orderBy('due_date')->get();
        $this->assertCount(12, $cards);
        $this->assertSame(
            ['2027-01-31', '2027-02-28', '2027-03-31', '2027-04-30', '2027-05-31'],
            $cards->take(5)->map(fn ($c) => $c->due_date->toDateString())->all()
        );

        $first = $cards->first();
        $this->assertSame('Aluguel', $first->title);
        $this->assertSame('Sala comercial', $first->description);
        $this->assertSame($empresa->id, $first->empresa_id);
        $this->assertSame(1500.0, (float) $first->estimated_value);
        $this->assertNull($first->event_id);
        $this->assertSame(CardOrigin::FixedCost, $first->origin);
        // Sem coluna no cadastro, nasce na coluna de entrada do quadro.
        $this->assertSame($board->columns()->where('is_entry', true)->value('id'), $first->board_column_id);
    }

    public function test_coluna_de_destino_escolhida_e_respeitada_e_coluna_de_outro_quadro_e_recusada(): void
    {
        $admin = $this->user();
        $board = $this->board();
        $other = $this->board('Outro custo fixo');
        $empresa = $this->empresa();
        $pago = $board->columns()->where('name', 'Pago')->first();

        $this->actingAs($admin)
            ->post(route('fixed-costs.store'), $this->payload($board, $empresa, [
                'board_column_id' => $other->columns()->first()->id,
            ]))
            ->assertSessionHasErrors('board_column_id');

        $this->actingAs($admin)
            ->post(route('fixed-costs.store'), $this->payload($board, $empresa, [
                'board_column_id' => $pago->id, 'installments_total' => 2,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame([$pago->id], Card::pluck('board_column_id')->unique()->values()->all());
    }

    public function test_validacoes_do_cadastro(): void
    {
        $admin = $this->user();
        $fixedBoard = $this->board();
        $eventBoard = $this->board('Orçamentos', false);
        $empresa = $this->empresa();

        // Quadro de evento não é destino.
        $this->actingAs($admin)
            ->post(route('fixed-costs.store'), $this->payload($eventBoard, $empresa))
            ->assertSessionHasErrors('board_id');

        // Modo "X dias antes" exige X.
        $this->actingAs($admin)
            ->post(route('fixed-costs.store'), $this->payload($fixedBoard, $empresa, [
                'generation_mode' => FixedCostGenerationMode::LeadDays->value, 'lead_days' => '',
            ]))
            ->assertSessionHasErrors('lead_days');

        // Começar na parcela não passa da quantidade; empresa e valor são obrigatórios.
        $this->actingAs($admin)
            ->post(route('fixed-costs.store'), $this->payload($fixedBoard, $empresa, [
                'start_installment' => 13, 'empresa_id' => '', 'installment_value' => '0,00',
            ]))
            ->assertSessionHasErrors(['start_installment', 'empresa_id', 'installment_value']);

        $this->assertSame(0, FixedCost::count());
    }

    public function test_permissoes_do_cadastro(): void
    {
        $this->get(route('fixed-costs.index'))->assertRedirect(route('login'));

        $this->actingAs($this->user('usuario'))->get(route('fixed-costs.index'))->assertForbidden();
        $this->actingAs($this->scopedUser())->get(route('fixed-costs.index'))->assertForbidden();
        $this->actingAs($this->scopedUser([AccessModule::FixedCosts]))->get(route('fixed-costs.index'))->assertOk();
        $this->actingAs($this->user('coordenador'))->get(route('fixed-costs.index'))->assertOk();
        $this->actingAs($this->user())->get(route('fixed-costs.create'))->assertOk();
    }

    public function test_listagem_e_detalhe_mostram_progresso_e_parcelas(): void
    {
        $admin = $this->user();
        $cost = $this->makeCost($this->board(), $this->empresa(), [
            'name' => 'Empréstimo', 'installments_total' => 5, 'start_installment' => 3,
        ]);

        $this->actingAs($admin)->get(route('fixed-costs.index', ['search' => 'Empr']))
            ->assertOk()->assertSee('Empréstimo')->assertSee('Todas geradas');

        $this->actingAs($admin)->get(route('fixed-costs.show', $cost))
            ->assertOk()
            ->assertSee('Paga fora do sistema')
            ->assertSee('3 de 5');
    }

    public function test_exclusao_bloqueada_com_parcela_gerada_e_soft_delete_sem_parcela(): void
    {
        $admin = $this->user();
        $board = $this->board();
        $empresa = $this->empresa();

        $used = $this->makeCost($board, $empresa);
        $this->travelTo('2026-11-18');
        $free = $this->makeCost($board, $empresa, [
            'name' => 'Água', 'generation_mode' => FixedCostGenerationMode::LeadDays->value, 'lead_days' => 5,
        ]);
        $this->assertSame(0, $free->fresh()->generatedNumbers()->count());

        $this->actingAs($admin)->from(route('fixed-costs.index'))
            ->delete(route('fixed-costs.destroy', $used))
            ->assertSessionHas('error');
        $this->assertNotSoftDeleted($used);

        $this->actingAs($admin)->delete(route('fixed-costs.destroy', $free))->assertSessionHas('success');
        $this->assertSoftDeleted($free);
    }

    public function test_edicao_trava_inicio_e_vencimento_e_limita_a_quantidade(): void
    {
        $admin = $this->user();
        $board = $this->board();
        $empresa = $this->empresa();
        $cost = $this->makeCost($board, $empresa, ['installments_total' => 6]);

        $this->actingAs($admin)->get(route('fixed-costs.edit', $cost))->assertOk()
            ->assertSee('Travado depois da primeira parcela gerada.')
            ->assertSee('Aplicar também às parcelas já geradas e não concluídas');

        $this->actingAs($admin)
            ->put(route('fixed-costs.update', $cost), $this->payload($board, $empresa, ['installments_total' => 5]))
            ->assertSessionHasErrors(['installments_total' => 'Já existem parcelas geradas até a 6. A quantidade mínima é 6.']);

        $this->actingAs($admin)
            ->put(route('fixed-costs.update', $cost), $this->payload($board, $empresa, [
                'installments_total' => 6, 'start_installment' => 2, 'first_due_date' => '2030-05-10', 'name' => 'Aluguel sala 2',
            ]))
            ->assertSessionHasNoErrors();

        $cost->refresh();
        $this->assertSame('Aluguel sala 2', $cost->name);
        $this->assertSame(1, $cost->start_installment);
        $this->assertSame('2027-01-31', $cost->first_due_date->toDateString());
    }

    public function test_edicao_so_propaga_para_cards_em_aberto_quando_marcado(): void
    {
        $admin = $this->user();
        $board = $this->board();
        $empresa = $this->empresa();
        $cost = $this->makeCost($board, $empresa, ['installments_total' => 3]);
        [$open, $concluded, $archived] = Card::orderBy('due_date')->get()->all();
        $concluded->update(['concluded_at' => now()]);
        $archived->update(['archived_at' => now()]);

        $this->actingAs($admin)
            ->put(route('fixed-costs.update', $cost), $this->payload($board, $empresa, [
                'installments_total' => 3, 'installment_value' => '1.800,00',
            ]))
            ->assertSessionHasNoErrors();
        $this->assertSame(1500.0, (float) $open->fresh()->estimated_value);

        $this->actingAs($admin)
            ->put(route('fixed-costs.update', $cost), $this->payload($board, $empresa, [
                'installments_total' => 3, 'installment_value' => '1.800,00', 'name' => 'Aluguel reajustado', 'apply_to_generated' => '1',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1800.0, (float) $open->fresh()->estimated_value);
        $this->assertSame('Aluguel reajustado', $open->fresh()->title);
        $this->assertSame(1500.0, (float) $concluded->fresh()->estimated_value);
        $this->assertSame('Aluguel', $archived->fresh()->title);
    }

    public function test_trocar_para_todas_agora_gera_as_pendentes(): void
    {
        $this->travelTo('2026-11-18');
        $admin = $this->user();
        $board = $this->board();
        $empresa = $this->empresa();
        $cost = $this->makeCost($board, $empresa, [
            'installments_total' => 4, 'generation_mode' => FixedCostGenerationMode::LeadDays->value, 'lead_days' => 10,
        ]);
        $this->assertSame(0, Card::count());

        $this->actingAs($admin)
            ->put(route('fixed-costs.update', $cost), $this->payload($board, $empresa, ['installments_total' => 4]))
            ->assertSessionHasNoErrors();

        $this->assertSame(4, Card::count());
    }
}
