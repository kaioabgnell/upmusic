<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AccessProfile>
 */
class AccessProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Perfil '.fake()->unique()->bothify('????-####'),
        ];
    }
}
