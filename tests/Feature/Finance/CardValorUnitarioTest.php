<?php

namespace Tests\Feature\Finance;

use App\Actions\Finance\SyncCardToFinance;
use App\Domain\Enums\CardNegociado;
use App\Domain\Enums\PessoaTipo;
use App\Domain\Enums\UnidadeMedida;
use App\Models\Fornecedor;
use App\Models\PriceRecord;

/**
 * Valor unitário no card (specs/23 §2, specs/15).
 *
 * O card só sabia falar em TOTAL, e a linha de custo é `unitário × quantidade × diárias`. Todo card
 * virava "1 × 1 × total": a coluna "Vlr. unit." nunca teve um unitário de verdade e o Banco de
 * Preços guardava totais de contratações de tamanhos diferentes — comparar "a diária de limpeza
 * custou 290 aqui e 280 lá" era impossível, que é justamente o que o comparativo precisa responder.
 */
class CardValorUnitarioTest extends FinanceTestCase
{
    private function fornecedorComCategoria(): Fornecedor
    {
        $categoria = $this->categoria('Limpeza');
        $categoria->update(['unidade' => UnidadeMedida::Diaria->value]);

        return Fornecedor::create([
            'type' => PessoaTipo::PJ->value,
            'name' => 'Limpeza Total',
            'document' => '11222333000181',
            'fornecedor_categoria_id' => $categoria->id,
            'active' => true,
        ]);
    }

    public function test_unitario_e_quantidade_do_card_viram_unitario_e_quantidade_da_linha(): void
    {
        // 20 diárias a R$ 290 — o exemplo da cliente. O que importa é a linha guardar 290 como
        // unitário (comparável) e 5.800 como total, não 5.800 na coluna de unitário.
        $card = $this->card($this->board(), $this->event(), [
            'unit_value' => 290,
            'quantity' => 20,
            // Previsto é o TOTAL orçado: 20 diárias a 310.
            'valor_com_nota' => 6200,
            'negociado' => CardNegociado::ComNota->value,
        ]);

        $item = app(SyncCardToFinance::class)->execute($card->fresh(), $this->user());

        $this->assertEquals(290, (float) $item->unit_actual);
        $this->assertEquals(20, (float) $item->quantity);
        $this->assertEquals(1, (float) $item->daily_count);

        // Totais são colunas geradas: unitário × quantidade × diárias.
        $this->assertEquals(5800, (float) $item->total_actual);
        $this->assertEquals(310, (float) $item->unit_estimated_1);
        $this->assertEquals(6200, (float) $item->total_estimated_1);
    }

    /**
     * O caso que apareceu em produção: card de 2 diárias, unitário R$ 210, previsto sem nota
     * R$ 420 (o TOTAL do orçamento). O previsto ia inteiro para a coluna unitária e a linha
     * multiplicava por 2 de novo — Total previsto R$ 840 e Resumo Geral com o dobro do custo.
     */
    public function test_previsto_total_do_card_vira_unitario_e_nao_dobra_o_custo(): void
    {
        $card = $this->card($this->board(), $this->event(), [
            'estimated_value' => 235,
            'quantity' => 2,
            'unit_value' => 210,
            'valor_sem_nota' => 420,
        ]);

        $item = app(SyncCardToFinance::class)->execute($card->fresh(), $this->user());

        $this->assertEquals(210, (float) $item->unit_estimated_1);
        $this->assertEquals(420, (float) $item->total_estimated_1);
        $this->assertEquals(210, (float) $item->unit_actual);
        $this->assertEquals(420, (float) $item->total_actual);
    }

    /** O modal precisa mostrar o MESMO número que o Financeiro vai gravar — não o total do card. */
    public function test_modal_mostra_o_previsto_por_unidade_e_nao_o_total(): void
    {
        $card = $this->card($this->board(), $this->event(), [
            'quantity' => 2,
            'unit_value' => 210,
            'valor_sem_nota' => 420,
        ]);

        $payload = $this->actingAs($this->user())
            ->getJson(route('cards.finance.preview', $card))
            ->assertOk()
            ->json('card');

        $this->assertEquals(210, $payload['unit_estimated']);
    }

    public function test_previsto_vem_do_valor_negociado_e_nao_do_banco_de_precos(): void
    {
        $card = $this->card($this->board(), $this->event(), [
            'estimated_value' => 400,   // referência do Banco de Preços
            'valor_sem_nota' => 280,
            'negociado' => CardNegociado::SemNota->value,
        ]);

        $item = app(SyncCardToFinance::class)->execute($card->fresh(), $this->user());

        $this->assertEquals(280, (float) $item->unit_estimated_1);
    }

    /** Sem negociação marcada vale o que estiver preenchido — com nota primeiro (é o comprovável). */
    public function test_sem_negociacao_marcada_o_previsto_usa_o_valor_preenchido(): void
    {
        $comNota = $this->card($this->board(), $this->event(), ['valor_com_nota' => 500, 'valor_sem_nota' => 450]);
        $semNota = $this->card($this->board(), $this->event(), ['valor_sem_nota' => 450]);

        $action = app(SyncCardToFinance::class);

        $this->assertEquals(500, (float) $action->execute($comNota->fresh(), $this->user())->unit_estimated_1);
        $this->assertEquals(450, (float) $action->execute($semNota->fresh(), $this->user())->unit_estimated_1);
    }

    /**
     * Regressão do erro que existia por construção: o card mandava um TOTAL para a coluna unitária,
     * e a cada edição do card o total da linha era multiplicado de novo pelas diárias/quantidade que
     * o financeiro tinha ajustado — uma despesa de 1.000 virava 15.000 sem ninguém digitar nada.
     */
    public function test_editar_card_nao_infla_linha_que_o_financeiro_ja_desdobrou(): void
    {
        $card = $this->card($this->board(), $this->event(), ['unit_value' => 100, 'quantity' => 1]);
        $item = app(SyncCardToFinance::class)->execute($card->fresh(), $this->user());

        // O financeiro desdobra a linha na grade: 3 diárias × 5 equipes.
        $item->update(['daily_count' => 3, 'quantity' => 5]);
        $this->assertEquals(1500, (float) $item->refresh()->total_actual);

        // Editar o card reespelha o UNITÁRIO (110), não o total — a multiplicação segue sendo do
        // financeiro, e o que ele ajustou continua de pé.
        $card->update(['unit_value' => 110]);

        $item->refresh();
        $this->assertEquals(110, (float) $item->unit_actual);
        $this->assertEquals(3, (float) $item->daily_count);
        $this->assertEquals(5, (float) $item->quantity);
        $this->assertEquals(1650, (float) $item->total_actual);
    }

    /** Card antigo (sem unitário) só tem o total: copiá-lo para a coluna unitária inflaria a linha. */
    public function test_card_sem_unitario_nao_sobrescreve_linha_ja_desdobrada(): void
    {
        $card = $this->card($this->board(), $this->event(), ['actual_value' => 1000]);
        $item = app(SyncCardToFinance::class)->execute($card->fresh(), $this->user());
        $this->assertEquals(1000, (float) $item->unit_actual);

        $item->update(['daily_count' => 3, 'quantity' => 5]);

        $card->update(['actual_value' => 1200]);

        // Continua com o unitário que o financeiro validou — 1.200 ali dentro viraria R$ 18.000.
        $this->assertEquals(1000, (float) $item->refresh()->unit_actual);
    }

    public function test_total_realizado_do_card_e_derivado_do_unitario(): void
    {
        $user = $this->user();
        $card = $this->card($this->board(), $this->event());

        $this->actingAs($user)
            ->putJson(route('cards.update', $card), [
                'title' => $card->title,
                'event_id' => $card->event_id,
                'unit_value' => '290,00',
                'quantity' => '20',
                'priority' => 'media',
            ])
            ->assertOk();

        $card->refresh();
        $this->assertEquals(290, (float) $card->unit_value);
        $this->assertEquals(20, (float) $card->quantity);
        $this->assertEquals(5800, (float) $card->actual_value);
    }

    public function test_banco_de_precos_guarda_o_unitario_e_nao_o_total_contratado(): void
    {
        $user = $this->user();
        $fornecedor = $this->fornecedorComCategoria();
        $card = $this->card($this->board(), $this->event(), ['fornecedor_id' => $fornecedor->id]);

        $this->actingAs($user)
            ->putJson(route('cards.update', $card), [
                'title' => $card->title,
                'event_id' => $card->event_id,
                'fornecedor_id' => $fornecedor->id,
                'unit_value' => '290,00',
                'quantity' => '20',
                'priority' => 'media',
            ])
            ->assertOk();

        // 290 (o preço da diária), não 5.800 (o tamanho desta contratação): é o unitário que
        // permite comparar este fornecedor com outro em outro evento.
        $this->assertEquals(290, (float) PriceRecord::where('card_id', $card->id)->sole()->price);
    }

    public function test_formulario_do_card_mostra_unitario_quantidade_e_os_previstos_renomeados(): void
    {
        $board = $this->board();

        $html = $this->actingAs($this->user())
            ->get(route('boards.show', $board))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('>Valor unitário<', $html);
        $this->assertStringContainsString('>Quantidade<', $html);
        $this->assertStringContainsString('>Valor previsto sem nota<', $html);
        $this->assertStringContainsString('>Valor previsto com nota<', $html);
        $this->assertStringContainsString('x-model="form.quantity"', $html);
        $this->assertStringContainsString('form.unit_value = maskMoneyDigits($event.target.value)', $html);

        // Os rótulos antigos não podem sobreviver em lugar nenhum: "Valor sem nota" agora é o
        // previsto por unidade, e deixar os dois nomes circulando confunde quem preenche.
        $this->assertStringNotContainsString('>Valor sem nota<', $html);
        $this->assertStringNotContainsString('>Valor com nota<', $html);
    }

    public function test_modal_do_financeiro_sugere_os_dois_unitarios_do_card(): void
    {
        $card = $this->card($this->board(), $this->event(), [
            'unit_value' => 290,
            'quantity' => 20,
            'valor_com_nota' => 6200,
            'negociado' => CardNegociado::ComNota->value,
        ]);

        $payload = $this->actingAs($this->user())
            ->getJson(route('cards.finance.preview', $card))
            ->assertOk()
            ->json('card');

        $this->assertEquals(310, $payload['unit_estimated']);
        $this->assertEquals(290, $payload['unit_actual']);
        $this->assertEquals(20, $payload['quantity']);
    }
}
