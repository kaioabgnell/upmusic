<?php

namespace Tests\Feature\Rentals;

use App\Actions\Rentals\CreateRentalOrder;
use App\Models\RentalItem;
use App\Models\RentalOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Base comum dos testes da Gestão de Locações (specs/24). */
abstract class RentalTestCase extends TestCase
{
    use RefreshDatabase;

    /** @param list<\App\Domain\Enums\AccessModule> $modules */
    protected function user(string $role = 'admin', array $attributes = [], array $modules = []): User
    {
        return $this->userAs($role, $attributes, $modules);
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
