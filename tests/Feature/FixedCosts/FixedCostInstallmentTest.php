<?php

namespace Tests\Feature\FixedCosts;

use App\Domain\Enums\FixedCostGenerationMode;
use App\Models\Card;
use App\Models\FixedCost;
use App\Models\FixedCostInstallment;

/** Exclusão de parcela (specs/27 §5.5) e "Gerar card novamente" (§3.4). */
class FixedCostInstallmentTest extends FixedCostTestCase
{
    /** [número => vencimento] do cronograma. */
    private function schedule(FixedCost $cost): array
    {
        return $cost->installments()->get()
            ->mapWithKeys(fn ($i) => [$i->number => $i->due_date->toDateString()])->all();
    }

    private function installment(FixedCost $cost, int $number): FixedCostInstallment
    {
        return $cost->installments()->where('number', $number)->firstOrFail();
    }

    public function test_excluir_parcela_pendente_renumera_as_seguintes_e_mantem_os_vencimentos(): void
    {
        $this->travelTo('2026-11-01');
        $admin = $this->user();
        $cost = $this->makeCost($this->board(), $this->empresa(), [
            'installments_total' => 5, 'first_due_date' => '2027-01-31',
            'generation_mode' => FixedCostGenerationMode::LeadDays->value, 'lead_days' => 10,
        ]);

        $this->actingAs($admin)
            ->delete(route('fixed-costs.installments.destroy', [$cost, $this->installment($cost, 2)]))
            ->assertSessionHas('success', 'Parcela 2 excluída. Agora são 4 parcelas.');

        $this->assertSame(4, $cost->fresh()->installments_total);
        $this->assertSame(
            [1 => '2027-01-31', 2 => '2027-03-31', 3 => '2027-04-30', 4 => '2027-05-31'],
            $this->schedule($cost)
        );
    }

    public function test_excluir_parcela_gerada_manda_o_card_para_a_lixeira_e_renumera_os_selos(): void
    {
        $admin = $this->user();
        $board = $this->board();
        $cost = $this->makeCost($board, $this->empresa(), ['installments_total' => 4]);
        $deleted = $this->installment($cost, 2)->card;
        $wasThird = $this->installment($cost, 3)->card;

        $this->actingAs($admin)
            ->delete(route('fixed-costs.installments.destroy', [$cost, $this->installment($cost, 2)]))
            ->assertSessionHas('success');

        $this->assertSoftDeleted($deleted);
        $this->assertSame(3, $cost->fresh()->installments_total);
        $this->assertSame('2027-03-31', $wasThird->fresh()->due_date->toDateString());

        $this->actingAs($admin)->getJson(route('cards.show', $wasThird))->assertOk()
            ->assertJson(['installment' => ['number' => 2, 'total' => 3]]);
    }

    public function test_parcela_paga_nao_pode_ser_excluida(): void
    {
        $admin = $this->user();
        $board = $this->board();
        $cost = $this->makeCost($board, $this->empresa(), ['installments_total' => 3]);

        // Concluído.
        $this->installment($cost, 1)->card->update(['concluded_at' => now()]);
        // Na etapa final do quadro ("Pago").
        $this->installment($cost, 2)->card->update(['board_column_id' => $board->columns()->where('is_final', true)->value('id')]);

        foreach ([1, 2] as $number) {
            $this->actingAs($admin)->from(route('fixed-costs.show', $cost))
                ->delete(route('fixed-costs.installments.destroy', [$cost, $this->installment($cost, $number)]))
                ->assertSessionHas('error', "A parcela {$number} já foi paga e não pode ser excluída.");
        }

        $this->assertSame(3, $cost->fresh()->installments_total);
        $this->assertSame(3, Card::count());
    }

    public function test_unica_parcela_nao_pode_ser_excluida(): void
    {
        $admin = $this->user();
        $cost = $this->makeCost($this->board(), $this->empresa(), ['installments_total' => 1]);

        $this->actingAs($admin)
            ->delete(route('fixed-costs.installments.destroy', [$cost, $this->installment($cost, 1)]))
            ->assertSessionHas('error', 'Esta é a única parcela. Inative ou exclua o custo fixo.');
    }

    public function test_parcela_de_outro_custo_fixo_da_404(): void
    {
        $admin = $this->user();
        $board = $this->board();
        $empresa = $this->empresa();
        $cost = $this->makeCost($board, $empresa, ['installments_total' => 2]);
        $other = $this->makeCost($board, $empresa, ['name' => 'Água', 'installments_total' => 2]);

        $this->actingAs($admin)
            ->delete(route('fixed-costs.installments.destroy', [$cost, $this->installment($other, 1)]))
            ->assertNotFound();
    }

    public function test_renovar_depois_de_excluir_continua_do_mes_seguinte_a_ultima(): void
    {
        $admin = $this->user();
        $cost = $this->makeCost($this->board(), $this->empresa(), ['installments_total' => 12]);

        $this->actingAs($admin)->delete(route('fixed-costs.installments.destroy', [$cost, $this->installment($cost, 4)]));
        $this->actingAs($admin)->post(route('fixed-costs.renew', $cost), ['extra' => 1]);

        $schedule = $this->schedule($cost);
        $this->assertCount(12, $schedule);
        $this->assertSame('2027-05-31', $schedule[4]);
        $this->assertSame('2027-12-31', $schedule[11]);
        $this->assertSame('2028-01-31', $schedule[12]);
    }

    public function test_card_excluido_pode_ser_gerado_novamente(): void
    {
        $admin = $this->user();
        $cost = $this->makeCost($this->board(), $this->empresa(), ['installments_total' => 2]);
        $installment = $this->installment($cost, 2);
        $old = $installment->card;
        $old->delete();

        $this->actingAs($admin)->get(route('fixed-costs.show', $cost))
            ->assertOk()->assertSee('Card excluído')->assertSee('Gerar card novamente');

        $this->actingAs($admin)
            ->post(route('fixed-costs.installments.regenerate', [$cost, $installment]))
            ->assertSessionHas('success');

        $new = $installment->fresh()->card;
        $this->assertNotSame($old->id, $new->id);
        $this->assertFalse($new->trashed());
        $this->assertSame('2027-02-28', $new->due_date->toDateString());
        $this->assertSame($installment->id, $new->fixed_cost_installment_id);
        $this->assertNull(Card::withTrashed()->find($old->id)->fixed_cost_installment_id);

        // Com card ativo, não gera outro.
        $this->actingAs($admin)
            ->post(route('fixed-costs.installments.regenerate', [$cost, $installment]))
            ->assertSessionHas('error', 'A parcela 2 já tem um card ativo.');
        $this->assertSame(2, Card::count());
    }

    public function test_diminuir_o_total_remove_so_do_fim_e_preserva_a_exclusao(): void
    {
        $this->travelTo('2026-11-01');
        $admin = $this->user();
        $board = $this->board();
        $empresa = $this->empresa();
        $cost = $this->makeCost($board, $empresa, [
            'installments_total' => 5, 'first_due_date' => '2027-01-10',
            'generation_mode' => FixedCostGenerationMode::LeadDays->value, 'lead_days' => 10,
        ]);
        $this->actingAs($admin)->delete(route('fixed-costs.installments.destroy', [$cost, $this->installment($cost, 2)]));

        $this->actingAs($admin)
            ->put(route('fixed-costs.update', $cost), $this->payload($board, $empresa, [
                'installments_total' => 3, 'first_due_date' => '2027-01-10',
                'generation_mode' => FixedCostGenerationMode::LeadDays->value, 'lead_days' => 10,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame([1 => '2027-01-10', 2 => '2027-03-10', 3 => '2027-04-10'], $this->schedule($cost));
    }
}
