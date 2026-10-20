<?php

namespace App\Actions\Rentals;

use App\Domain\Enums\RentalOrderLogType;
use App\Domain\Enums\RentalOrderStatus;
use App\Models\RentalOrder;
use App\Models\User;
use App\Services\Rentals\RentalAvailabilityService;
use Illuminate\Validation\ValidationException;

/**
 * Máquina de estados do pedido de locação (specs/24 §6) — único ponto de mudança de etapa entre
 * Orçamento, Confirmada, Em uso e Devolvida (drag no Kanban, seletor do drawer). Encerrar, reabrir,
 * cancelar e reativar têm Actions dedicadas, porque exigem dados extras (receita realizada, motivo).
 */
class MoveRentalOrder
{
    public function __construct(
        private RefreshAvailabilityWarnings $warnings,
        private CheckRentalReturn $returns,
        private LogRentalOrderEvent $logger,
        private RentalAvailabilityService $availability,
    ) {}

    /** @param  array{returns?: array<int,int>}  $context */
    public function execute(RentalOrder $order, RentalOrderStatus $target, ?User $actor = null, array $context = []): RentalOrder
    {
        if ($order->status->isLocked()) {
            throw ValidationException::withMessages([
                'status' => 'Este pedido está encerrado ou cancelado. Reabra ou reative antes de mover.',
            ]);
        }

        if ($target->isLocked()) {
            throw ValidationException::withMessages([
                'status' => 'Use as ações de Encerrar ou Cancelar para esta etapa.',
            ]);
        }

        $from = $order->status;
        if ($from === $target) {
            return $order;
        }

        $flow = RentalOrderStatus::flow();
        $fromIndex = $from->order();
        $toIndex = $target->order();

        if ($toIndex > $fromIndex) {
            // Avançando: roda o gate de cada etapa atravessada, na ordem (specs/24 §2.1 — pular etapas).
            for ($i = $fromIndex + 1; $i <= $toIndex; $i++) {
                $this->gate($order, $flow[$i], $context, $actor);
            }
            if ($flow[$fromIndex + 1] === RentalOrderStatus::Confirmada) {
                $this->logAvailabilityWarningIfAny($order, $actor);
            }
        } elseif ($fromIndex >= RentalOrderStatus::Devolvida->order() && $toIndex < RentalOrderStatus::Devolvida->order()) {
            // Voltando para antes de Devolvida: desfaz a conferência de devolução.
            $this->returns->clear($order, $actor);
        }

        $order->update(['status' => $target->value]);
        $order->refresh();

        $this->logger->execute($order, RentalOrderLogType::StatusChanged, $actor, null, $from, $target);

        if ($target->reservesStock()) {
            $this->warnings->execute($order);
        } elseif ($order->has_availability_warning) {
            $order->update(['has_availability_warning' => false]);
        }

        return $order->refresh();
    }

    private function gate(RentalOrder $order, RentalOrderStatus $step, array $context, ?User $actor): void
    {
        match ($step) {
            RentalOrderStatus::Confirmada => $this->gateConfirmada($order),
            RentalOrderStatus::Devolvida => $this->gateDevolvida($order, $context, $actor),
            default => null,
        };
    }

    /** Cliente, período e ao menos uma linha (specs/24 §6). A falta de estoque NUNCA bloqueia aqui. */
    private function gateConfirmada(RentalOrder $order): void
    {
        $order->loadMissing('items');
        $errors = [];

        if (! $order->empresa_id && ! $order->client_name) {
            $errors[] = 'Informe o cliente antes de confirmar o pedido.';
        }
        if (! $order->start_at || ! $order->end_at) {
            $errors[] = 'Informe o período (retirada e devolução) antes de confirmar.';
        } elseif ($order->end_at->lessThanOrEqualTo($order->start_at)) {
            $errors[] = 'A devolução deve ser depois da retirada.';
        }
        if ($order->items->isEmpty()) {
            $errors[] = 'Adicione ao menos um item antes de confirmar.';
        }

        if ($errors) {
            throw ValidationException::withMessages(['rental_order' => $errors]);
        }
    }

    /**
     * Ao confirmar, grava o aviso de disponibilidade no histórico com a lista de faltas por item —
     * a confirmação em si nunca é bloqueada por isso (specs/24 §6).
     */
    private function logAvailabilityWarningIfAny(RentalOrder $order, ?User $actor): void
    {
        $order->loadMissing('items');
        if (! $order->start_at || ! $order->end_at) {
            return;
        }

        $shortages = [];
        foreach ($order->items as $line) {
            $available = $this->availability->availableQuantity($line->rental_item_id, $order->start_at, $order->end_at, $order->id);
            if ($line->quantity > $available) {
                $shortages[] = ['item' => $line->item_name, 'solicitado' => $line->quantity, 'disponivel' => $available];
            }
        }

        if ($shortages) {
            $this->logger->execute(
                $order, RentalOrderLogType::AvailabilityWarning, $actor,
                'Falta de itens detectada ao confirmar o pedido.', payload: $shortages,
            );
        }
    }

    /**
     * Quem passa pela rota dedicada de devolução já chamou `CheckRentalReturn::execute()` antes de
     * mover — aqui só preenchemos como "devolvido integralmente" quando o pedido pula direto para
     * Devolvida sem conferência prévia (ex.: avançar várias etapas de uma vez).
     */
    private function gateDevolvida(RentalOrder $order, array $context, ?User $actor): void
    {
        $order->loadMissing('items');
        if ($order->items->every(fn ($l) => $l->returned_quantity === null)) {
            $this->returns->execute($order, $context['returns'] ?? [], $actor);
        }
    }
}
