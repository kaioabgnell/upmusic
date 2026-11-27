<?php

namespace App\Policies;

use App\Domain\Enums\AccessModule;
use App\Models\User;

/** Contas bancárias (specs/26): módulo `bank_accounts` do perfil de acesso (specs/28). Admin via Gate::before. */
class BankAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasModule(AccessModule::BankAccounts);
    }

    public function create(User $user): bool
    {
        return $user->hasModule(AccessModule::BankAccounts);
    }

    public function update(User $user): bool
    {
        return $user->hasModule(AccessModule::BankAccounts);
    }

    public function delete(User $user): bool
    {
        return $user->hasModule(AccessModule::BankAccounts);
    }
}
