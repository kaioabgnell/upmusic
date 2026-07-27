<?php

namespace Tests\Unit\Bid;

use App\Domain\DTOs\BidAptitudeResult;
use App\Domain\DTOs\BidMatchResult;
use App\Domain\Enums\BidCompanySize;
use App\Domain\Enums\BidMatchStatus;
use App\Domain\Enums\BidRequirementKind;
use App\Domain\Enums\BidVerdict;
use App\Models\BidCompany;
use App\Models\BidNotice;
use App\Models\BidNoticeRequirement;
use App\Services\Bid\AptitudeScorer;
use Tests\TestCase;

/** Pontuação, veredito e ranking (specs/21 §10.4) — determinísticos, sem IA e sem banco. */
class AptitudeScorerTest extends TestCase
{
    private AptitudeScorer $scorer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scorer = new AptitudeScorer;
    }

    private function requirement(bool $mandatory = true, BidRequirementKind $kind = BidRequirementKind::Documento): BidNoticeRequirement
    {
        return new BidNoticeRequirement([
            'kind' => $kind,
            'name' => 'Requisito '.uniqid(),
            'mandatory' => $mandatory,
            'source_excerpt' => 'trecho',
        ]);
    }

    private function notice(): BidNotice
    {
        return new BidNotice(['title' => 'Edital', 'object_summary' => null, 'source' => 'texto']);
    }

    private function company(): BidCompany
    {
        $company = new BidCompany(['corporate_name' => 'EMPRESA X', 'size' => BidCompanySize::Me->value]);
        // Sem a relação carregada o scorer não tenta afinidade de ramo (evita lazy loading).
        $company->setRelation('businessLines', collect());

        return $company;
    }

    private function evaluate(array $rows): BidAptitudeResult
    {
        return $this->scorer->evaluate($this->notice(), $this->company(), collect($rows));
    }

    public function test_apta_quando_todos_os_obrigatorios_estao_atendidos(): void
    {
        $result = $this->evaluate([
            ['requirement' => $this->requirement(), 'result' => BidMatchResult::atendido('ok')],
            ['requirement' => $this->requirement(), 'result' => BidMatchResult::atendido('ok')],
        ]);

        $this->assertSame(BidVerdict::Apta, $result->verdict);
        $this->assertSame(100.0, $result->score);
        $this->assertSame(2, $result->metCount);
        $this->assertSame([], $result->blockers);
        $this->assertContains('Todos os requisitos obrigatórios atendidos.', $result->highlights);
    }

    public function test_vencendo_gera_pendencia_e_credito_parcial(): void
    {
        $vencendo = new BidMatchResult(BidMatchStatus::Vencendo, 'vence em 12 dias', daysToExpire: 12);

        $result = $this->evaluate([
            ['requirement' => $this->requirement(), 'result' => BidMatchResult::atendido('ok')],
            ['requirement' => $this->requirement(), 'result' => $vencendo],
        ]);

        $this->assertSame(BidVerdict::AptaComPendencias, $result->verdict);
        // Pesos 3 e 3; créditos 1,0 e 0,75 -> 87,5.
        $this->assertSame(87.5, $result->score);
        $this->assertSame(1, $result->expiringCount);
        $this->assertSame(12, $result->minDaysToExpire);
    }

    public function test_vencimento_critico_pesa_menos_que_vencendo_normal(): void
    {
        $critico = new BidMatchResult(BidMatchStatus::Vencendo, 'vence em 3 dias', critical: true, daysToExpire: 3);

        $result = $this->evaluate([['requirement' => $this->requirement(), 'result' => $critico]]);

        $this->assertSame(50.0, $result->score);
    }

    public function test_obrigatorio_ausente_bloqueia_e_torna_inapta(): void
    {
        $result = $this->evaluate([
            ['requirement' => $this->requirement(), 'result' => BidMatchResult::atendido('ok')],
            ['requirement' => $this->requirement(), 'result' => BidMatchResult::ausente('Nenhuma CND Federal no acervo.')],
        ]);

        $this->assertSame(BidVerdict::Inapta, $result->verdict);
        $this->assertCount(1, $result->blockers);
        $this->assertStringContainsString('Documento obrigatório ausente', $result->blockers[0]);
        $this->assertSame(1, $result->missingCount);
    }

    public function test_requisito_estrutural_nao_atendido_derruba_score_alto(): void
    {
        $rows = [];
        // Nove documentos obrigatórios em ordem...
        for ($i = 0; $i < 9; $i++) {
            $rows[] = ['requirement' => $this->requirement(), 'result' => BidMatchResult::atendido('ok')];
        }
        // ...e um CNAE incompatível: acervo impecável, mas a empresa não pode participar.
        $rows[] = [
            'requirement' => $this->requirement(true, BidRequirementKind::Cnae),
            'result' => BidMatchResult::ausente('CNAE 8011-1/01 não consta no cadastro da empresa.'),
        ];

        $result = $this->evaluate($rows);

        $this->assertSame(BidVerdict::Inapta, $result->verdict);
        $this->assertSame(90.0, $result->score);
        $this->assertStringContainsString('Requisito da empresa não atendido', $result->blockers[0]);
    }

    public function test_conferir_fica_fora_do_denominador_mas_impede_apta(): void
    {
        $result = $this->evaluate([
            ['requirement' => $this->requirement(), 'result' => BidMatchResult::atendido('ok')],
            ['requirement' => $this->requirement(), 'result' => BidMatchResult::conferir('conferir teor do atestado')],
        ]);

        // Score de 100 porque o `conferir` não entra no cálculo...
        $this->assertSame(100.0, $result->score);
        // ...mas o veredito não pode ser "Apta" com pendência obrigatória em aberto.
        $this->assertSame(BidVerdict::AptaComPendencias, $result->verdict);
        $this->assertSame(1, $result->reviewCount);
        $this->assertSame([], $result->blockers);
    }

    public function test_nao_aplicavel_e_ignorado_por_completo(): void
    {
        $result = $this->evaluate([
            ['requirement' => $this->requirement(), 'result' => BidMatchResult::atendido('ok')],
            ['requirement' => $this->requirement(), 'result' => BidMatchResult::naoAplicavel('dispensado')],
        ]);

        $this->assertSame(BidVerdict::Apta, $result->verdict);
        $this->assertSame(100.0, $result->score);
        $this->assertSame(1, $result->metCount);
        $this->assertSame(0, $result->reviewCount);
    }

    public function test_opcional_faltando_nao_bloqueia_mas_gera_pendencia(): void
    {
        $result = $this->evaluate([
            ['requirement' => $this->requirement(), 'result' => BidMatchResult::atendido('ok')],
            ['requirement' => $this->requirement(false), 'result' => BidMatchResult::ausente('sem documento')],
        ]);

        $this->assertSame(BidVerdict::AptaComPendencias, $result->verdict);
        $this->assertSame([], $result->blockers);
        // Pesos 3 (obrigatório) e 1 (opcional) -> 3/4 = 75.
        $this->assertSame(75.0, $result->score);
    }

    public function test_ranking_ordena_por_veredito_score_e_folga_de_vencimento(): void
    {
        $apta = new BidAptitudeResult(BidVerdict::Apta, 90, 5, 0, 0, 0, [], [], 100);
        $aptaMenorFolga = new BidAptitudeResult(BidVerdict::Apta, 90, 5, 0, 0, 0, [], [], 10);
        $pendencias = new BidAptitudeResult(BidVerdict::AptaComPendencias, 99, 5, 1, 0, 0, [], [], 5);
        $inapta = new BidAptitudeResult(BidVerdict::Inapta, 95, 5, 0, 1, 0, ['bloqueio'], [], null);

        $ranked = $this->scorer->rank(collect([
            ['company' => new BidCompany(['corporate_name' => 'C']), 'result' => $inapta],
            ['company' => new BidCompany(['corporate_name' => 'B']), 'result' => $pendencias],
            ['company' => new BidCompany(['corporate_name' => 'A2']), 'result' => $aptaMenorFolga],
            ['company' => new BidCompany(['corporate_name' => 'A1']), 'result' => $apta],
        ]));

        $this->assertSame(
            ['A1', 'A2', 'B', 'C'],
            $ranked->map(fn ($row) => $row['company']->corporate_name)->all()
        );
    }
}
