<?php

namespace Tests\Unit\Bid;

use App\Domain\Enums\BidDocumentStatus;
use App\Models\BidDocument;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Regra central do módulo (specs/21 §10.1): o status de vigência é sempre derivado de
 * `expires_at`/`no_expiry` na leitura — nunca lido de coluna.
 */
class BidDocumentStatusTest extends TestCase
{
    public static function statusProvider(): array
    {
        return [
            'vencido ontem' => [-1, BidDocumentStatus::Vencido, 'Vencido há 1 dia'],
            'vencido há 10 dias' => [-10, BidDocumentStatus::Vencido, 'Vencido há 10 dias'],
            'vence hoje' => [0, BidDocumentStatus::Vencendo, 'Vence hoje'],
            'crítico (7 dias)' => [7, BidDocumentStatus::Vencendo, 'Vence em 7 dias'],
            'vencendo (30 dias)' => [30, BidDocumentStatus::Vencendo, 'Vence em 30 dias'],
            'válido (31 dias)' => [31, BidDocumentStatus::Valido, 'Válido — faltam 31 dias'],
            'válido (400 dias)' => [400, BidDocumentStatus::Valido, 'Válido — faltam 400 dias'],
        ];
    }

    /** @dataProvider statusProvider */
    public function test_status_e_rotulo_por_dias_ate_o_vencimento(int $days, BidDocumentStatus $expected, string $label): void
    {
        $document = new BidDocument([
            'no_expiry' => false,
            'expires_at' => Carbon::today()->addDays($days),
        ]);

        $this->assertSame($expected, $document->status);
        $this->assertSame($label, $document->status_label);
        $this->assertSame($days, $document->days_to_expire);
    }

    public function test_documento_sem_validade_e_permanente(): void
    {
        $document = new BidDocument(['no_expiry' => true, 'expires_at' => null]);

        $this->assertSame(BidDocumentStatus::Permanente, $document->status);
        $this->assertSame('Sem validade', $document->status_label);
        $this->assertNull($document->days_to_expire);
        $this->assertFalse($document->is_critical);
    }

    public function test_vencimento_critico_so_dentro_da_janela_configurada(): void
    {
        config(['licitacoes.critical_days' => 7]);

        $critico = new BidDocument(['no_expiry' => false, 'expires_at' => Carbon::today()->addDays(5)]);
        $naoCritico = new BidDocument(['no_expiry' => false, 'expires_at' => Carbon::today()->addDays(20)]);
        $vencido = new BidDocument(['no_expiry' => false, 'expires_at' => Carbon::today()->subDay()]);

        $this->assertTrue($critico->is_critical);
        $this->assertFalse($naoCritico->is_critical);
        // Já vencido não é "crítico": é problema consumado, e a UI o mostra em vermelho.
        $this->assertFalse($vencido->is_critical);
    }

    public function test_virada_do_dia_nao_deixa_documento_de_hoje_como_vencido(): void
    {
        // Um documento que vence hoje ainda vale hoje — o corte é estritamente < hoje.
        Carbon::setTestNow(Carbon::parse('2026-07-26 23:59:59'));

        $document = new BidDocument(['no_expiry' => false, 'expires_at' => Carbon::parse('2026-07-26')]);
        $this->assertSame(BidDocumentStatus::Vencendo, $document->status);

        Carbon::setTestNow(Carbon::parse('2026-07-27 00:00:01'));
        $document = new BidDocument(['no_expiry' => false, 'expires_at' => Carbon::parse('2026-07-26')]);
        $this->assertSame(BidDocumentStatus::Vencido, $document->status);

        Carbon::setTestNow();
    }

    public function test_limiar_de_vencendo_respeita_a_configuracao(): void
    {
        config(['licitacoes.expiring_days' => 60]);

        $document = new BidDocument(['no_expiry' => false, 'expires_at' => Carbon::today()->addDays(45)]);

        $this->assertSame(BidDocumentStatus::Vencendo, $document->status);
    }
}
