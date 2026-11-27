<?php

namespace App\Actions\Users;

use App\Models\User;

/**
 * Grava o acesso do usuário (specs/28 §7.2): Administrador não tem perfil nem eventos; quem não é
 * admin tem um perfil de acesso e "Eventos visíveis" (todos = nenhum vínculo em `event_user`).
 */
class SyncUserAccess
{
    /** @param array{is_admin?: bool, access_profile_id?: int|null, event_scope?: string, events?: list<int>} $data */
    public function execute(User $user, array $data): void
    {
        if (! empty($data['is_admin'])) {
            $user->update(['is_admin' => true, 'access_profile_id' => null]);
            $user->events()->detach();
        } else {
            $user->update(['is_admin' => false, 'access_profile_id' => $data['access_profile_id']]);
            $selected = ($data['event_scope'] ?? 'all') === 'selected' ? ($data['events'] ?? []) : [];
            $user->events()->sync($selected);
        }

        $user->flushAccessCache();
    }
}
