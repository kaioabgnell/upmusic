<?php

namespace App\Actions\AccessProfiles;

use App\Models\AccessProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Exclui um perfil de acesso. Bloqueado enquanto houver usuários vinculados (specs/28 §7.1): o Admin
 * troca o perfil deles antes. Usuários na lixeira não bloqueiam — ficam sem perfil e, se forem
 * restaurados, o cadastro pede um perfil novo.
 */
class DeleteAccessProfile
{
    public function execute(AccessProfile $profile): void
    {
        $linked = $profile->users()->count();

        if ($linked > 0) {
            throw ValidationException::withMessages([
                'profile' => "Este perfil está vinculado a {$linked} usuário(s). Troque o perfil deles antes de excluir.",
            ]);
        }

        DB::transaction(function () use ($profile) {
            User::onlyTrashed()->where('access_profile_id', $profile->id)->update(['access_profile_id' => null]);
            $profile->delete();
        });
    }
}
