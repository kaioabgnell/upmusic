<?php

namespace Database\Seeders;

use App\Actions\AccessProfiles\MigrateRolesToAccessProfiles;
use App\Domain\Enums\AccessModule;
use App\Models\AccessProfile;
use App\Models\Setor;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $orcamentos = Setor::where('nome', 'Orçamentos')->first();

        // Perfis de acesso iniciais (specs/28 §9): os mesmos pacotes da migração dos papéis antigos.
        $coordenador = $this->profile('Coordenador', MigrateRolesToAccessProfiles::COORDINATOR_MODULES);
        $orcamentosProfile = $this->profile('Orçamentos', MigrateRolesToAccessProfiles::USER_MODULES);

        User::updateOrCreate(['email' => 'admin@upmusic.local'], [
            'name' => 'Administrador',
            'password' => Hash::make('password'),
            'is_admin' => true,
            'access_profile_id' => null,
            'active' => true,
        ]);

        User::updateOrCreate(['email' => 'coordenador@upmusic.local'], [
            'name' => 'Coordenador',
            'password' => Hash::make('password'),
            'is_admin' => false,
            'access_profile_id' => $coordenador->id,
            'setor_id' => $orcamentos?->id,
            'active' => true,
        ]);

        // O quadro Orçamentos é ligado no perfil pelo BoardSeeder, que cria os quadros.
        User::updateOrCreate(['email' => 'usuario@upmusic.local'], [
            'name' => 'Usuário Operação',
            'password' => Hash::make('password'),
            'is_admin' => false,
            'access_profile_id' => $orcamentosProfile->id,
            'setor_id' => $orcamentos?->id,
            'active' => true,
        ]);
    }

    /** @param list<AccessModule> $modules */
    private function profile(string $name, array $modules): AccessProfile
    {
        $profile = AccessProfile::firstOrCreate(['name' => $name]);
        foreach ($modules as $module) {
            $profile->modules()->firstOrCreate(['module' => $module->value]);
        }

        return $profile;
    }
}
