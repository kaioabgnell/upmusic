<?php

namespace App\Domain\Enums;

/** Como as parcelas de um custo fixo viram cards (specs/27 §2.1). */
enum FixedCostGenerationMode: string
{
    case AllUpfront = 'all_upfront';
    case LeadDays = 'lead_days';

    public function label(): string
    {
        return match ($this) {
            self::AllUpfront => 'Gerar todas as parcelas agora',
            self::LeadDays => 'Gerar cada parcela X dias antes do vencimento',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $m) => ['value' => $m->value, 'label' => $m->label()], self::cases());
    }
}
