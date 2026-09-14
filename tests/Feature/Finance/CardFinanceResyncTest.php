<?php

namespace Tests\Feature\Finance;

use App\Actions\Finance\SyncCardToFinance;
use App\Domain\Enums\CardNegociado;
use App\Domain\Enums\PessoaTipo;
use App\Models\Fornecedor;

/**
 * Bug: um card já enviado ao Financeiro (linha existente, `finance_cost_items.card_id`) não
 * refletia edições feitas depois no próprio card — título, fornecedor, "Valor sem nota", "Valor
 * com nota" e "Valor realizado" ficavam presos no valor de quando o card foi enviado, e só uma
 * nova visita manual ao modal "Sincronizar com o Financeiro" atualizava a linha.
 *
 * A causa: `CardObserver::updated()` só re-executava `SyncCardToFinance` quando `board_id` mudava
 * (card entrando num quadro `feeds_finance`) — nenhum outro campo disparava a resincronia, e
 * `SyncCardToFinance::execute()` só atualizava a linha existente quando vinha `$overrides` do
 * modal (nunca a partir de uma edição comum do card). Corrigido em ambos: o Observer agora reage
 * também aos campos espelhados na linha, e a Action sempre reespelha o card na linha existente.
 */
class CardFinanceResyncTest extends FinanceTestCase
{
    private function fornecedor(string $name = 'Som & Cia', string $document = '11222333000181'): Fornecedor
    {
        return Fornecedor::create([
            'type' => PessoaTipo::PJ->value,
            'name' => $name,
            'document' => $document,
            'active' => true,
        ]);
    }

    public function test_editar_valores_do_card_ja_vinculado_atualiza_a_linha_sozinho(): void
    {
        $card = $this->card($this->board(), $this->event(), ['estimated_value' => 1000]);
        $item = app(SyncCardToFinance::class)->execute($card->fresh(), $this->user());
        $this->assertEquals(1000, (float) $item->unit_estimated_1);

        $card->update([
            'estimated_value' => 1500,
            'valor_sem_nota' => 900,
            'valor_com_nota' => 950,
            'negociado' => CardNegociado::ComNota->value,
            'unit_value' => 940,
        ]);

        // Previsto = o valor negociado (com nota, porque é o cenário marcado no card); realizado =
        // o valor unitário. O "Banco de Preços" (1500) deixou de alimentar o previsto: é referência
        // de consulta, não o preço combinado com este fornecedor.
        $item->refresh();
        $this->assertEquals(950, (float) $item->unit_estimated_1);
        $this->assertEquals(940, (float) $item->unit_actual);
    }

    public function test_editar_titulo_e_fornecedor_do_card_ja_vinculado_atualiza_a_linha_sozinho(): void
    {
        $card = $this->card($this->board(), $this->event());
        $item = app(SyncCardToFinance::class)->execute($card->fresh(), $this->user());
        $this->assertSame('Locação de som', $item->description);
        $this->assertNull($item->fornecedor_id);

        $fornecedor = $this->fornecedor();
        $categoria = $this->categoria('Som');
        $fornecedor->update(['fornecedor_categoria_id' => $categoria->id]);

        $card->update(['title' => 'Locação de som — Palco B', 'fornecedor_id' => $fornecedor->id]);

        $item->refresh();
        $this->assertSame('Locação de som — Palco B', $item->description);
        $this->assertSame($fornecedor->id, $item->fornecedor_id);
        $this->assertSame($categoria->id, $item->fornecedor_categoria_id);
    }

    /** Editar um card SEM linha no Financeiro não deve criar uma linha — só o envio explícito cria. */
    public function test_editar_card_sem_linha_vinculada_nao_cria_linha_nova(): void
    {
        $card = $this->card($this->board(), $this->event());

        $card->update(['valor_sem_nota' => 500]);

        $this->assertDatabaseCount('finance_cost_items', 0);
    }

    /** Cobre o fluxo real (rota HTTP de edição do card), não só a Action isolada. */
    public function test_atualizar_card_pela_rota_http_propaga_para_o_financeiro(): void
    {
        $user = $this->user();
        $card = $this->card($this->board(), $this->event(), ['estimated_value' => 2000]);
        $item = app(SyncCardToFinance::class)->execute($card->fresh(), $user);

        $this->actingAs($user)
            ->putJson(route('cards.update', $card), [
                'title' => $card->title,
                'event_id' => $card->event_id,
                'estimated_value' => '3.000,00',
                'actual_value' => '2.800,00',
                'priority' => 'media',
            ])
            ->assertOk();

        $item->refresh();
        $this->assertEquals(3000, (float) $item->unit_estimated_1);
        $this->assertEquals(2800, (float) $item->unit_actual);
    }

    /** Editar um campo qualquer não espelhado (ex.: prazo) não deve gerar comentário de resincronia. */
    public function test_editar_campo_nao_espelhado_nao_gera_comentario_no_card(): void
    {
        $card = $this->card($this->board(), $this->event());
        app(SyncCardToFinance::class)->execute($card->fresh(), $this->user());
        $before = $card->comments()->count();

        $card->update(['due_date' => now()->addWeek()->toDateString()]);

        $this->assertSame($before, $card->comments()->count());
    }
}
