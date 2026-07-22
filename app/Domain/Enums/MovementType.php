<?php

namespace App\Domain\Enums;

enum MovementType: string
{
    case Column = 'column';
    case Board = 'board';
    case Conclusion = 'conclusion';
    case Reopening = 'reopening';
    case Archival = 'archival';
    case Unarchival = 'unarchival';
    case Approval = 'approval';
    case Rejection = 'rejection';
    case MinutaRecebida = 'minuta_recebida';

    public function label(): string
    {
        return match ($this) {
            self::Column => 'Mudança de etapa',
            self::Board => 'Transferência de departamento',
            self::Conclusion => 'Card concluído',
            self::Reopening => 'Card reaberto',
            self::Archival => 'Card arquivado',
            self::Unarchival => 'Card desarquivado',
            self::Approval => 'Card aprovado',
            self::Rejection => 'Card reprovado (arquivado)',
            self::MinutaRecebida => 'Minuta recebida do fornecedor',
        };
    }
}
