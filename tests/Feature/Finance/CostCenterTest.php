<?php

namespace Tests\Feature\Finance;

use App\Actions\Finance\SyncCardToFinance;
use App\Domain\Enums\AccessModule;
use App\Models\CostCenter;
use App\Models\FinanceCostItem;
use App\Services\Finance\FinanceExportService;
use PhpOffice\PhpSpreadsheet\IOFactory;

/** Centro de custo (specs/25): cadastro + uso em custos, receitas e na ponte do card. */
class CostCenterTest extends FinanceTestCase
{
    private function center(string $name = 'Marketing', ?string $code = 'MKT-01', bool $active = true): CostCenter
    {
        return CostCenter::create(['code' => $code, 'name' => $name, 'active' => $active]);
    }

    // ---- Cadastro ----------------------------------------------------------

    public function test_admin_cria_edita_e_lista_centros_de_custo(): void
    {
        $admin = $this->user();

        $this->actingAs($admin)
            ->post(route('cost-centers.store'), ['code' => ' MKT-01 ', 'name' => 'Marketing', 'active' => '1'])
            ->assertRedirect(route('cost-centers.index'));

        $center = CostCenter::firstOrFail();
        $this->assertSame('MKT-01', $center->code);
        $this->assertSame('MKT-01 Marketing', $center->label);

        $this->actingAs($admin)
            ->put(route('cost-centers.update', $center), ['code' => '', 'name' => 'Marketing digital'])
            ->assertRedirect(route('cost-centers.index'));

        $center->refresh();
        $this->assertNull($center->code);
        $this->assertFalse($center->active);
        $this->assertSame('Marketing digital', $center->label);

        $this->actingAs($admin)->get(route('cost-centers.index', ['search' => 'digital']))
            ->assertOk()->assertSee('Marketing digital');
    }

    public function test_codigo_e_nome_sao_unicos_so_entre_os_nao_excluidos(): void
    {
        $admin = $this->user();
        $old = $this->center();

        $this->actingAs($admin)
            ->post(route('cost-centers.store'), ['code' => 'MKT-01', 'name' => 'Marketing'])
            ->assertSessionHasErrors(['code', 'name']);

        $old->delete();

        $this->actingAs($admin)
            ->post(route('cost-centers.store'), ['code' => 'MKT-01', 'name' => 'Marketing'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, CostCenter::count());
        $this->assertSame(2, CostCenter::withTrashed()->count());
    }

    public function test_exclusao_bloqueada_quando_em_uso_e_soft_delete_quando_livre(): void
    {
        $admin = $this->user();
        $used = $this->center();
        $free = $this->center('Produção', 'PRD');

        $this->sheet()->costItems()->create(['description' => 'Banner', 'cost_center_id' => $used->id]);

        $this->actingAs($admin)->from(route('cost-centers.index'))
            ->delete(route('cost-centers.destroy', $used))
            ->assertRedirect(route('cost-centers.index'))
            ->assertSessionHas('error');
        $this->assertNotSoftDeleted($used);

        $this->actingAs($admin)->delete(route('cost-centers.destroy', $free))->assertSessionHas('success');
        $this->assertSoftDeleted($free);
    }

    /** specs/28: o módulo do perfil decide; "Eventos visíveis" não esconde cadastro que não é de evento. */
    public function test_gerenciar_depende_do_modulo_centros_de_custo(): void
    {
        $this->actingAs($this->user('usuario'))
            ->get(route('cost-centers.index'))->assertForbidden();

        $semModulo = $this->user('sem-acesso', modules: [AccessModule::BankAccounts]);
        $this->actingAs($semModulo)
            ->post(route('cost-centers.store'), ['name' => 'Qualquer'])->assertForbidden();

        $scoped = $this->user('sem-acesso', modules: [AccessModule::CostCenters]);
        $scoped->events()->attach($this->event());
        $this->actingAs($scoped->fresh())
            ->get(route('cost-centers.index'))->assertOk();

        $this->actingAs($this->user('coordenador'))
            ->get(route('cost-centers.index'))->assertOk();
    }

    // ---- Custos e receitas -------------------------------------------------

    public function test_linha_de_custo_aceita_centro_ativo_e_recusa_inativo_novo(): void
    {
        $admin = $this->user();
        $active = $this->center();
        $inactive = $this->center('Antigo', 'OLD', false);
        $item = $this->sheet()->costItems()->create(['description' => 'Banner']);

        $this->actingAs($admin)
            ->putJson(route('finance.costs.update', $item), ['description' => 'Banner', 'cost_center_id' => $inactive->id])
            ->assertUnprocessable()->assertJsonValidationErrors('cost_center_id');

        $this->actingAs($admin)
            ->putJson(route('finance.costs.update', $item), ['description' => 'Banner', 'cost_center_id' => $active->id])
            ->assertOk()
            ->assertJsonPath('cost_center_id', $active->id)
            ->assertJsonPath('cost_center', 'MKT-01 Marketing');
    }

    public function test_centro_inativado_depois_continua_valido_na_linha_que_ja_o_usa(): void
    {
        $admin = $this->user();
        $center = $this->center();
        $item = $this->sheet()->costItems()->create(['description' => 'Banner', 'cost_center_id' => $center->id]);
        $center->update(['active' => false]);

        // O autosave reenvia a linha inteira — inclusive o centro que ela já tem.
        $this->actingAs($admin)
            ->putJson(route('finance.costs.update', $item), ['description' => 'Banner novo', 'cost_center_id' => $center->id])
            ->assertOk()
            ->assertJsonPath('cost_center_id', $center->id);
    }

    public function test_filtro_por_centro_de_custo_inclui_a_opcao_sem_centro(): void
    {
        $admin = $this->user();
        $event = $this->event();
        $sheet = $this->sheet($event);
        $mkt = $this->center();
        $prd = $this->center('Produção', 'PRD');

        $sheet->costItems()->create(['description' => 'Linha marketing', 'cost_center_id' => $mkt->id]);
        $sheet->costItems()->create(['description' => 'Linha producao', 'cost_center_id' => $prd->id]);
        $sheet->costItems()->create(['description' => 'Linha sem centro']);

        $rows = fn (array $filter) => $this->costRows($event, $filter, $admin)->pluck('description')->sort()->values()->all();

        $this->assertSame(['Linha marketing'], $rows(['cost_center_id' => [$mkt->id]]));
        $this->assertSame(['Linha marketing', 'Linha sem centro'], $rows(['cost_center_id' => [$mkt->id, 'none']]));
        $this->assertSame(['Linha sem centro'], $rows(['cost_center_id' => ['none']]));
    }

    public function test_receita_recebe_e_limpa_o_centro_de_custo(): void
    {
        $admin = $this->user();
        $center = $this->center();
        $revenue = $this->sheet()->revenues()->create(['category' => 'patrocinio']);

        $payload = ['category' => 'patrocinio', 'cost_center_id' => $center->id];

        $this->actingAs($admin)->putJson(route('finance.revenues.update', $revenue), $payload)
            ->assertOk()->assertJsonPath('cost_center_id', $center->id);

        $this->actingAs($admin)->putJson(route('finance.revenues.update', $revenue), ['cost_center_id' => null] + $payload)
            ->assertOk()->assertJsonPath('cost_center_id', null);
    }

    // ---- Ponte do card -----------------------------------------------------

    public function test_modal_do_card_grava_o_centro_e_a_resincronia_automatica_nao_mexe(): void
    {
        $admin = $this->user();
        $event = $this->event();
        $card = $this->card($this->board(), $event);
        $center = $this->center();

        $this->actingAs($admin)
            ->postJson(route('cards.finance.sync', $card), ['event_id' => $event->id, 'cost_center_id' => $center->id])
            ->assertCreated();

        $item = FinanceCostItem::where('card_id', $card->id)->firstOrFail();
        $this->assertSame($center->id, $item->cost_center_id);

        // Gatilho automático (observer/quadro) não manda a chave: o centro fica como está.
        app(SyncCardToFinance::class)->execute($card->fresh(), $admin);
        $this->assertSame($center->id, $item->fresh()->cost_center_id);

        $this->actingAs($admin)->getJson(route('cards.finance.preview', $card))
            ->assertOk()
            ->assertJsonPath('existing_item.cost_center_id', $center->id)
            ->assertJsonPath('cost_centers.0.label', 'MKT-01 Marketing');

        // Modal com o campo vazio = tirar o centro da linha.
        $this->actingAs($admin)
            ->postJson(route('cards.finance.sync', $card), ['cost_center_id' => null])
            ->assertCreated();
        $this->assertNull($item->fresh()->cost_center_id);
    }

    public function test_preview_inclui_o_centro_inativo_que_a_linha_ja_tem(): void
    {
        $admin = $this->user();
        $event = $this->event();
        $card = $this->card($this->board(), $event);
        $this->center('Ativo', 'A1');
        $inactive = $this->center('Antigo', 'OLD', false);

        $this->actingAs($admin)->getJson(route('cards.finance.preview', $card))
            ->assertOk()->assertJsonCount(1, 'cost_centers');

        $this->sheet($event)->costItems()->create([
            'description' => 'Locação de som', 'card_id' => $card->id, 'cost_center_id' => $inactive->id,
        ]);

        $this->actingAs($admin)->getJson(route('cards.finance.preview', $card))
            ->assertOk()
            ->assertJsonCount(2, 'cost_centers')
            ->assertJsonFragment(['label' => 'OLD Antigo (inativo)', 'active' => false]);
    }

    // ---- Exportação --------------------------------------------------------

    public function test_exportacao_traz_o_centro_de_custo_na_ultima_coluna(): void
    {
        $sheet = $this->sheet();
        $center = $this->center();
        $sheet->costItems()->create(['description' => 'Banner', 'cost_center_id' => $center->id]);
        $sheet->revenues()->create(['category' => 'patrocinio', 'cost_center_id' => $center->id]);

        $book = IOFactory::load(app(FinanceExportService::class)->toXlsx($sheet->refresh()));

        $costs = $book->getSheetByName('CUSTOS');
        $lastColumn = $costs->getHighestColumn(7);
        $this->assertSame('CENTRO DE CUSTO', $costs->getCell($lastColumn.'7')->getValue());
        $this->assertSame('MKT-01 Marketing', $costs->getCell($lastColumn.'8')->getValue());
        // As colunas fixas que o import lê por posição continuam no lugar.
        $this->assertSame('DESCRIÇÃO', $costs->getCell('B7')->getValue());

        $revenues = $book->getSheetByName('RECEITAS');
        $this->assertSame('CENTRO DE CUSTO', $revenues->getCell('H3')->getValue());
        // A planilha já nasce com linhas de receita padrão: procura a linha na coluna inteira.
        $column = array_column($revenues->rangeToArray('H4:H'.$revenues->getHighestRow()), 0);
        $this->assertContains('MKT-01 Marketing', $column);
    }
}
