<?php

namespace Database\Seeders;

use App\Domain\Enums\UserRole;
use App\Models\Setor;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $orcamentos = Setor::where('nome', 'Orçamentos')->first();

        User::updateOrCreate(['email' => 'admin@upmusic.local'], [
            'name' => 'Administrador',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin->value,
            'active' => true,
        ]);

        User::updateOrCreate(['email' => 'coordenador@upmusic.local'], [
            'name' => 'Coordenador',
            'password' => Hash::make('password'),
            'role' => UserRole::Coordenador->value,
            'setor_id' => $orcamentos?->id,
            'active' => true,
        ]);

        User::updateOrCreate(['email' => 'usuario@upmusic.local'], [
            'name' => 'Usuário Operação',
            'password' => Hash::make('password'),
            'role' => UserRole::Usuario->value,
            'setor_id' => $orcamentos?->id,
            'active' => true,
        ]);
    }
}
