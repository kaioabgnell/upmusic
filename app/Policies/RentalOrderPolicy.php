<?php

namespace App\Policies;

use App\Domain\Enums\AccessModule;
use App\Models\RentalOrder;
use App\Models\User;

/**
 * Pedidos de locação (specs/24): módulo `rentals` do perfil de acesso (specs/28), que libera tudo —
 * inclusive encerrar, reabrir, reativar e excluir. Admin via `Gate::before`.
 *
 * O travamento do pedido encerrado/cancelado é responsabilidade do controller
 * (`RentalController::authorizeWrite()`), não desta policy — igual ao Financeiro do Evento.
 */
class RentalOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasModule(AccessModule::Rentals);
    }

    public function view(User $user, RentalOrder $order): bool
    {
        return $user->hasModule(AccessModule::Rentals);
    }

    public function create(User $user): bool
    {
        return $user->hasModule(AccessModule::Rentals);
    }

    public function update(User $user, RentalOrder $order): bool
    {
        return $user->hasModule(AccessModule::Rentals);
    }

    public function cancel(User $user, RentalOrder $order): bool
    {
        return $user->hasModule(AccessModule::Rentals);
    }

    public function close(User $user, RentalOrder $order): bool
    {
        return $user->hasModule(AccessModule::Rentals);
    }

    public function reopen(User $user, RentalOrder $order): bool
    {
        return $user->hasModule(AccessModule::Rentals);
    }

    public function reactivate(User $user, RentalOrder $order): bool
    {
        return $user->hasModule(AccessModule::Rentals);
    }

    public function delete(User $user, RentalOrder $order): bool
    {
        return $user->hasModule(AccessModule::Rentals);
    }
}
