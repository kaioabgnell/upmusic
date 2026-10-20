<?php

namespace App\Domain\Enums;

/** Tipo de entrada no histórico do pedido de locação (specs/24 §4.7 / §6). */
enum RentalOrderLogType: string
{
    case Created = 'created';
    case StatusChanged = 'status_changed';
    case AvailabilityWarning = 'availability_warning';
    case ReturnChecked = 'return_checked';
    case ReturnCleared = 'return_cleared';
    case Closed = 'closed';
    case Reopened = 'reopened';
    case Cancelled = 'cancelled';
    case Reactivated = 'reactivated';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Pedido criado',
            self::StatusChanged => 'Etapa alterada',
            self::AvailabilityWarning => 'Aviso de disponibilidade',
            self::ReturnChecked => 'Conferência de devolução',
            self::ReturnCleared => 'Conferência de devolução desfeita',
            self::Closed => 'Pedido encerrado',
            self::Reopened => 'Pedido reaberto',
            self::Cancelled => 'Pedido cancelado',
            self::Reactivated => 'Pedido reativado',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Created => 'fa-plus',
            self::StatusChanged => 'fa-arrow-right-arrow-left',
            self::AvailabilityWarning => 'fa-triangle-exclamation',
            self::ReturnChecked => 'fa-clipboard-check',
            self::ReturnCleared => 'fa-rotate-left',
            self::Closed => 'fa-lock',
            self::Reopened => 'fa-lock-open',
            self::Cancelled => 'fa-ban',
            self::Reactivated => 'fa-rotate-right',
        };
    }
}
