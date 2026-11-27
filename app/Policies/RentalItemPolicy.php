<?php

namespace App\Policies;

use App\Domain\Enums\AccessModule;
use App\Models\RentalItem;
use App\Models\User;

/**
 * Inventário da Gestão de Locações (specs/24): módulo `rentals` do perfil de acesso (specs/28), que
 * libera o módulo inteiro, inclusive cadastrar/editar/excluir itens. Admin via `Gate::before`.
 */
class RentalItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasModule(AccessModule::Rentals);
    }

    public function view(User $user, RentalItem $item): bool
    {
        return $user->hasModule(AccessModule::Rentals);
    }

    public function create(User $user): bool
    {
        return $user->hasModule(AccessModule::Rentals);
    }

    public function update(User $user, RentalItem $item): bool
    {
        return $user->hasModule(AccessModule::Rentals);
    }

    public function delete(User $user, RentalItem $item): bool
    {
        return $user->hasModule(AccessModule::Rentals);
    }
}
