<?php

namespace App\Domain\Enums;

/**
 * Etapas fixas do Kanban de Pedidos de Locação (specs/24 §5). Diferente das colunas configuráveis
 * dos quadros de processos — aqui os relatórios e a disponibilidade dependem de um enum estável.
 */
enum RentalOrderStatus: string
{
    case Orcamento = 'orcamento';
    case Confirmada = 'confirmada';
    case EmUso = 'em_uso';
    case Devolvida = 'devolvida';
    case Encerrada = 'encerrada';
    case Cancelada = 'cancelada';

    public function label(): string
    {
        return match ($this) {
            self::Orcamento => 'Orçamento',
            self::Confirmada => 'Confirmada',
            self::EmUso => 'Em uso',
            self::Devolvida => 'Devolvida',
            self::Encerrada => 'Encerrada',
            self::Cancelada => 'Cancelada',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Orcamento => 'fa-file-pen',
            self::Confirmada => 'fa-circle-check',
            self::EmUso => 'fa-truck-ramp-box',
            self::Devolvida => 'fa-rotate-left',
            self::Encerrada => 'fa-lock',
            self::Cancelada => 'fa-ban',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Orcamento => 'neutral',
            self::Confirmada => 'orange',
            self::EmUso => 'orange',
            self::Devolvida => 'success',
            self::Encerrada => 'dark',
            self::Cancelada => 'danger',
        };
    }

    /** As 5 colunas do Kanban, na ordem do fluxo. Cancelada fica fora (specs/24 §8.2). */
    public static function flow(): array
    {
        return [self::Orcamento, self::Confirmada, self::EmUso, self::Devolvida, self::Encerrada];
    }

    /** Confirmada e Em uso reservam estoque no período (specs/24 §7.3). */
    public function reservesStock(): bool
    {
        return in_array($this, [self::Confirmada, self::EmUso], true);
    }

    /** Encerrada e Cancelada travam a edição de dados/linhas/custos (specs/24 §6). */
    public function isLocked(): bool
    {
        return in_array($this, [self::Encerrada, self::Cancelada], true);
    }

    /** Posição no fluxo, para permitir avançar várias etapas de uma vez e validar os gates no caminho. */
    public function order(): int
    {
        $index = array_search($this, self::flow(), true);

        return $index === false ? -1 : $index;
    }

    /** @return array<string,string> value => label */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
