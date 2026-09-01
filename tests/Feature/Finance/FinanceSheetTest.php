<?php

namespace Tests\Feature\Finance;

use App\Domain\Enums\FinanceRevenueCategory;
use App\Models\FinanceSheet;

/** Ciclo de vida da planilha e renderização das telas (specs/23 §8). */
class FinanceSheetTest extends FinanceTestCase
{
    public function test_planilha_e_criada_sob_demanda_ao_abrir_o_resumo(): void
    {
        $event = $this->event();

        $this->assertSame(0, FinanceSheet::count());

        $this->actingAs($this->user())->get(route('finance.show', $event))->assertOk();

        $this->assertSame(1, FinanceSheet::where('event_id', $event->id)->count());
    }

    public function test_abrir_duas_vezes_nao_cria_duas_planilhas(): void
    {
        $event = $this->event();

        $this->actingAs($this->user())->get(route('finance.show', $event))->assertOk();
        $this->actingAs($this->user())->get(route('finance.costs.index', $event))->assertOk();

        $this->assertSame(1, FinanceSheet::count());
    }

    public function test_telas_de_custos_e_receitas_renderizam_com_dados(): void
    {
        $event = $this->event();
        $sheet = $this->sheet($event);
        $sheet->costItems()->create([
            'description' => 'Tenda 10x10',
            'fornecedor_categoria_id' => $this->categoria()->id,
            'daily_count' => 1, 'quantity' => 1, 'unit_estimated_1' => 1000, 'unit_actual' => 1100,
        ]);
        $this->source();

        $this->actingAs($this->user())
            ->get(route('finance.costs.index', $event))
            ->assertOk()
            ->assertSee('Tenda 10x10')
            ->assertSee('Controle');

        $this->actingAs($this->user())
            ->get(route('finance.revenues.index', $event))
            ->assertOk()
            ->assertSee('TOTAL GERAL');
    }

    public function test_criar_e_atualizar_linha_pela_grade(): void
    {
        $event = $this->event();
        $user = $this->user();

        $created = $this->actingAs($user)
            ->postJson(route('finance.costs.store', $event), [
                'description' => 'Gerador 260KVA',
                'daily_count' => '2',
                'quantity' => '3',
                'unit_estimated_1' => '1.250,50',
            ])
            ->assertCreated()
            ->json();

        // Valor em formato BR é normalizado antes da validação (Br::money).
        $this->assertEquals(1250.50, $created['unit_estimated_1']);
        $this->assertEquals(7503.00, $created['total_estimated_1']);

        $updated = $this->actingAs($user)
            ->putJson(route('finance.costs.update', $created['id']), [
                'description' => 'Gerador 500KVA',
                'daily_count' => '2',
                'quantity' => '3',
                'unit_estimated_1' => '1.250,50',
                'unit_actual' => '1.300,00',
            ])
            ->assertOk()
            ->json();

        $this->assertEquals(7800.00, $updated['total_actual']);
        $this->assertSame('Gerador 500KVA', $updated['description']);
    }

    public function test_duplicar_linha_nao_leva_o_vinculo_com_o_card(): void
    {
        $event = $this->event();
        $card = $this->card($this->board(), $event);
        $item = $this->sheet($event)->costItems()->create([
            'description' => 'Influencer', 'card_id' => $card->id, 'unit_estimated_1' => 500,
        ])->refresh();

        $copy = $this->actingAs($this->user())
            ->postJson(route('finance.costs.duplicate', $item))
            ->assertCreated()
            ->json();

        // Copiar o vínculo faria o mesmo comprovante provar duas despesas.
        $this->assertNull($copy['card_id']);
        $this->assertSame('Influencer', $copy['description']);
    }

    public function test_adicionar_e_remover_receita(): void
    {
        $event = $this->event();
        $user = $this->user();

        $created = $this->actingAs($user)
            ->postJson(route('finance.revenues.store', $event), [
                'category' => FinanceRevenueCategory::Patrocinio->value,
                'description' => 'Patrocinador B',
                'estimated_value' => '10.000,00',
                'actual_value' => '8.000,00',
                'received_value' => '3.000,00',
            ])
            ->assertCreated()
            ->json();

        $this->assertEquals(5000, $created['pending_value']);   // coluna gerada

        $this->actingAs($user)
            ->deleteJson(route('finance.revenues.destroy', $created['id']))
            ->assertOk();

        $this->assertSoftDeleted('finance_revenues', ['id' => $created['id']]);
    }

    public function test_fechar_prestacao_de_contas_torna_a_planilha_somente_leitura(): void
    {
        $event = $this->event();
        $sheet = $this->sheet($event);

        $this->actingAs($this->user())->post(route('finance.close', $event))->assertRedirect();

        $this->assertTrue($sheet->refresh()->isClosed());
        $this->assertNotNull($sheet->closed_at);
        $this->assertNotNull($sheet->closed_by);

        $this->actingAs($this->user())
            ->postJson(route('finance.costs.store', $event), ['description' => 'Nova'])
            ->assertStatus(422);
    }

    public function test_acerto_de_socios_espelha_o_que_foi_salvo(): void
    {
        $event = $this->event();
        $sheet = $this->sheet($event);
        $sheet->settlements()->create(['partner_name' => 'Antigo', 'percentage' => 100]);

        $this->actingAs($this->user())
            ->put(route('finance.settlements.sync', $event), [
                'partners' => [
                    ['partner_name' => 'Sócio A', 'percentage' => 50],
                    ['partner_name' => 'Sócio B', 'percentage' => 50],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(2, $sheet->settlements()->count());
        $this->assertSame(0, $sheet->settlements()->where('partner_name', 'Antigo')->count());
    }
}
