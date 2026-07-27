<?php

namespace App\Domain\Enums;

/** Natureza do requisito de habilitação extraído do edital (ver specs/21 §10.3). */
enum BidRequirementKind: string
{
    case Documento = 'documento';
    case Cnae = 'cnae';
    case Porte = 'porte';
    case CapitalSocial = 'capital_social';
    case PatrimonioLiquido = 'patrimonio_liquido';
    case AtestadoTecnico = 'atestado_tecnico';
    case RegistroProfissional = 'registro_profissional';
    case IndiceContabil = 'indice_contabil';
    case VisitaTecnica = 'visita_tecnica';
    case GarantiaProposta = 'garantia_proposta';
    case Outro = 'outro';

    public function label(): string
    {
        return match ($this) {
            self::Documento => 'Documento',
            self::Cnae => 'CNAE',
            self::Porte => 'Porte',
            self::CapitalSocial => 'Capital social',
            self::PatrimonioLiquido => 'Patrimônio líquido',
            self::AtestadoTecnico => 'Atestado técnico',
            self::RegistroProfissional => 'Registro profissional',
            self::IndiceContabil => 'Índice contábil',
            self::VisitaTecnica => 'Visita técnica',
            self::GarantiaProposta => 'Garantia de proposta',
            self::Outro => 'Outro',
        };
    }

    /**
     * Requisitos estruturais da empresa: quando não atendidos, eliminam a empresa
     * independentemente do acervo documental (ver specs/21 §10.4).
     */
    public function isStructural(): bool
    {
        return in_array($this, [self::Cnae, self::Porte, self::CapitalSocial, self::PatrimonioLiquido], true);
    }
}
