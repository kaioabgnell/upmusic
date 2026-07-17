<?php

namespace App\Domain\Enums;

enum FieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Money = 'money';
    case Date = 'date';
    case Select = 'select';
    case Checkbox = 'checkbox';
    case Email = 'email';
    case Phone = 'phone';
    case File = 'file';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Texto curto',
            self::Textarea => 'Texto longo',
            self::Number => 'Número',
            self::Money => 'Valor (R$)',
            self::Date => 'Data',
            self::Select => 'Seleção',
            self::Checkbox => 'Caixa de seleção',
            self::Email => 'E-mail',
            self::Phone => 'Telefone',
            self::File => 'Arquivo',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $t) => [$t->value => $t->label()])
            ->all();
    }
}
