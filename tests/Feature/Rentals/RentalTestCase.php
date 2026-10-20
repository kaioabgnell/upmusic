<?php

namespace Tests\Feature\Rentals;

use App\Actions\Rentals\CreateRentalOrder;
use App\Domain\Enums\UserRole;
use App\Models\RentalItem;
use App\Models\RentalOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Base comum dos testes da Gestão de Locações (specs/24). */
abstract class RentalTestCase extends TestCase
{
    use RefreshDatabase;

    protected function user(UserRole $role = UserRole::Admin, array $attributes = []): User
    {
        return User::factory()->create([
            'role' => $role->value,
            'active' => true,
        ] + $attributes)->fresh();
    }

    protected function item(array $attributes = []): RentalItem
    {
        return RentalItem::create($attributes + [
            'name' => 'Mesa redonda',
            'quantity' => 10,
            'unit_cost' => 300,
            'rental_price' => 50,
        ]);
    }

    protected function order(User $actor, array $attributes = []): RentalOrder
    {
        return app(CreateRentalOrder::class)->execute($attributes, $actor);
    }
}
