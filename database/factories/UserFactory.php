<?php

namespace Database\Factories;

use App\Actions\AccessProfiles\MigrateRolesToAccessProfiles;
use App\Domain\Enums\AccessModule;
use App\Models\AccessProfile;
use App\Models\Board;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /** Administrador (specs/28): vê e faz tudo, sem perfil de acesso. */
    public function admin(): static
    {
        return $this->state(fn () => ['is_admin' => true, 'access_profile_id' => null]);
    }

    /** Liga módulos no perfil de acesso do usuário (cria um perfil próprio se ele ainda não tiver). */
    public function withModules(AccessModule ...$modules): static
    {
        return $this->afterCreating(function (User $user) use ($modules) {
            $profile = $this->ensureProfile($user);
            foreach ($modules as $module) {
                $profile->modules()->firstOrCreate(['module' => $module->value]);
            }
        });
    }

    /** Liga quadros no perfil de acesso do usuário. */
    public function withBoards(Board ...$boards): static
    {
        return $this->afterCreating(function (User $user) use ($boards) {
            $this->ensureProfile($user)->boards()->syncWithoutDetaching(array_map(fn (Board $b) => $b->id, $boards));
        });
    }

    /** "Eventos visíveis: somente os selecionados" (specs/28 §6). */
    public function eventScoped(Event ...$events): static
    {
        return $this->afterCreating(function (User $user) use ($events) {
            $user->events()->syncWithoutDetaching(array_map(fn (Event $e) => $e->id, $events));
        });
    }

    /**
     * Perfil com o mesmo pacote que a migração dá ao antigo Coordenador sem eventos (specs/28 §9):
     * Configurar quadros, os cadastros, Financeiro dos Eventos, Banco de Preços e Locações.
     */
    public function coordenador(): static
    {
        return $this->withModules(...MigrateRolesToAccessProfiles::COORDINATOR_MODULES);
    }

    private function ensureProfile(User $user): AccessProfile
    {
        if ($user->access_profile_id) {
            return AccessProfile::findOrFail($user->access_profile_id);
        }

        $profile = AccessProfile::create(['name' => 'Perfil '.Str::random(12)]);
        $user->forceFill(['access_profile_id' => $profile->id])->save();

        return $profile;
    }
}
