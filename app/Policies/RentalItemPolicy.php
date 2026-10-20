<?php

namespace App\Policies;

use App\Models\RentalItem;
use App\Models\User;

/**
 * Acesso ao inventário da Gestão de Locações (specs/24 §11). Admin é liberado antes daqui pelo
 * `Gate::before`. Cadastrar/editar/excluir itens é exclusivo de quem gerencia o módulo — o
 * `usuario` habilitado só enxerga, nunca mexe no inventário.
 */
class RentalItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessRentals();
    }

    public function view(User $user, RentalItem $item): bool
    {
        return $user->canAccessRentals();
    }

    public function create(User $user): bool
    {
        return $user->canManageRentals();
    }

    public function update(User $user, RentalItem $item): bool
    {
        return $user->canManageRentals();
    }

    public function delete(User $user, RentalItem $item): bool
    {
        return $user->canManageRentals();
    }
}
