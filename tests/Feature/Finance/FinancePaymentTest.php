<?php

namespace Tests\Feature\Finance;

use App\Actions\Finance\RegisterPayment;
use App\Services\Finance\FinanceSummaryService;
use Illuminate\Validation\ValidationException;

/** PAGO / FALTA PAGAR e a quebra por grupo de pagamento (specs/23 §4.4 e §7). */
class FinancePaymentTest extends FinanceTestCase
{
    public function test_pago_e_falta_pagar_saem_da_soma_dos_pagamentos(): void
    {
        $sheet = $this->sheet();
        $item = $sheet->costItems()->create([
            'description' => 'Segurança | Efetivo',
            'daily_count' => 1, 'quantity' => 1, 'unit_estimated_1' => 5000, 'unit_actual' => 5000,
        ])->refresh();

        $caixa = $this->source('Caixa do Evento');
        $socio = $this->source('Sócio 1');

        // Pagamento parcial por duas fontes diferentes — a planilha só tinha colunas fixas.
        app(RegisterPayment::class)->execute($item, ['finance_payment_source_id' => $caixa->id, 'amount' => 3000]);
        app(RegisterPayment::class)->execute($item, ['finance_payment_source_id' => $socio->id, 'amount' => 1500]);

        $summary = app(FinanceSummaryService::class)->summary($sheet->refresh());

        $this->assertEquals(4500, $summary['progress']['paid']);
        $this->assertEquals(500, $summary['progress']['pending']);
        $this->assertEquals(90.0, $summary['progress']['pct']);
    }

    public function test_quebra_por_grupo_de_pagamento(): void
    {
        $sheet = $this->sheet();
        $item = $sheet->costItems()->create([
            'description' => 'Palco', 'daily_count' => 1, 'quantity' => 1,
            'unit_estimated_1' => 1000, 'unit_actual' => 1000,
        ])->refresh();

        app(RegisterPayment::class)->execute($item, [
            'finance_payment_source_id' => $this->source('Ticketeira')->id, 'amount' => 700,
        ]);

        $bySource = app(FinanceSummaryService::class)->byPaymentSource($sheet);

        $this->assertSame('Ticketeira', $bySource[0]['label']);
        $this->assertEquals(700, $bySource[0]['total']);
    }

    public function test_pagamento_com_planilha_fechada_e_recusado(): void
    {
        $sheet = $this->sheet();
        $item = $sheet->costItems()->create(['description' => 'Palco'])->refresh();
        $sheet->update(['status' => 'fechado']);

        $this->expectException(ValidationException::class);

        app(RegisterPayment::class)->execute($item->refresh(), [
            'finance_payment_source_id' => $this->source()->id, 'amount' => 100,
        ]);
    }

    public function test_pagamento_acima_do_realizado_e_registrado_e_aparece_como_alerta(): void
    {
        $sheet = $this->sheet();
        $item = $sheet->costItems()->create([
            'description' => 'Frete', 'daily_count' => 1, 'quantity' => 1,
            'unit_estimated_1' => 500, 'unit_actual' => 500,
        ])->refresh();

        app(RegisterPayment::class)->execute($item, [
            'finance_payment_source_id' => $this->source()->id, 'amount' => 800,
        ]);

        $alerts = collect(app(FinanceSummaryService::class)->alerts($sheet->refresh()));

        $this->assertTrue($alerts->contains(fn ($a) => str_contains($a['text'], 'pagamento acima do valor realizado')));
    }

    public function test_valor_zero_ou_negativo_e_rejeitado_pela_validacao(): void
    {
        $sheet = $this->sheet();
        $item = $sheet->costItems()->create(['description' => 'Frete'])->refresh();

        $this->actingAs($this->user())
            ->postJson(route('finance.payments.store', $item), [
                'finance_payment_source_id' => $this->source()->id,
                'amount' => '0',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');
    }
}
