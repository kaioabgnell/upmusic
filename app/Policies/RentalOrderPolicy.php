<?php

namespace App\Policies;

use App\Models\RentalOrder;
use App\Models\User;

/**
 * Acesso aos pedidos de locação (specs/24 §11). Admin é liberado antes daqui pelo `Gate::before`.
 *
 * `update` cobre dados, linhas, desconto, custos, mover etapas (exceto Encerrar) e cancelar — tudo
 * que o `usuario` habilitado também pode fazer. Encerrar, reabrir, reativar e excluir são exclusivos
 * de quem gerencia o módulo. O travamento do pedido encerrado/cancelado é responsabilidade do
 * controller (`RentalController::authorizeWrite()`), não desta policy — igual ao Financeiro do Evento.
 */
class RentalOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessRentals();
    }

    public function view(User $user, RentalOrder $order): bool
    {
        return $user->canAccessRentals();
    }

    public function create(User $user): bool
    {
        return $user->canAccessRentals();
    }

    public function update(User $user, RentalOrder $order): bool
    {
        return $user->canAccessRentals();
    }

    public function cancel(User $user, RentalOrder $order): bool
    {
        return $user->canAccessRentals();
    }

    public function close(User $user, RentalOrder $order): bool
    {
        return $user->canManageRentals();
    }

    public function reopen(User $user, RentalOrder $order): bool
    {
        return $user->canManageRentals();
    }

    public function reactivate(User $user, RentalOrder $order): bool
    {
        return $user->canManageRentals();
    }

    public function delete(User $user, RentalOrder $order): bool
    {
        return $user->canManageRentals();
    }
}
