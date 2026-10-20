<?php

namespace Tests\Feature\Rentals;

use App\Domain\Enums\RentalOrderStatus;
use App\Domain\Enums\UserRole;
use App\Models\RentalOrderItem;
use App\Services\Rentals\RentalAvailabilityService;

/** Disponibilidade por período (specs/24 §7.3) — só informa, nunca bloqueia. */
class RentalAvailabilityServiceTest extends RentalTestCase
{
    private function reserve(string $itemOwner, $item, string $start, string $end, string $status, int $qty): void
    {
        $order = $this->order($this->user(UserRole::Admin), [
            'client_name' => $itemOwner, 'start_at' => $start, 'end_at' => $end,
        ]);
        RentalOrderItem::create([
            'rental_order_id' => $order->id, 'rental_item_id' => $item->id,
            'item_name' => $item->name, 'quantity' => $qty, 'unit_price' => 10,
        ]);
        $order->update(['status' => $status]);
    }

    public function test_so_confirmada_e_em_uso_reservam(): void
    {
        $item = $this->item(['quantity' => 10]);
        $this->reserve('X', $item, '2026-12-01', '2026-12-05', RentalOrderStatus::Orcamento->value, 5);

        $available = app(RentalAvailabilityService::class)->availableQuantity($item->id, '2026-12-02', '2026-12-03');

        $this->assertSame(10, $available); // Orçamento não reserva.
    }

    public function test_periodos_que_nao_se_sobrepoem_nao_reservam(): void
    {
        $item = $this->item(['quantity' => 10]);
        $this->reserve('X', $item, '2026-12-01', '2026-12-05', RentalOrderStatus::Confirmada->value, 5);

        $available = app(RentalAvailabilityService::class)->availableQuantity($item->id, '2026-12-05', '2026-12-10');

        // [01,05) não sobrepõe [05,10) — a borda exata não conta como sobreposição.
        $this->assertSame(10, $available);
    }

    public function test_periodos_sobrepostos_reduzem_a_disponibilidade(): void
    {
        $item = $this->item(['quantity' => 10]);
        $this->reserve('X', $item, '2026-12-01', '2026-12-10', RentalOrderStatus::Confirmada->value, 6);

        $available = app(RentalAvailabilityService::class)->availableQuantity($item->id, '2026-12-05', '2026-12-06');

        $this->assertSame(4, $available);
    }

    public function test_resultado_pode_ser_negativo(): void
    {
        $item = $this->item(['quantity' => 3]);
        $this->reserve('X', $item, '2026-12-01', '2026-12-10', RentalOrderStatus::Confirmada->value, 5);

        $available = app(RentalAvailabilityService::class)->availableQuantity($item->id, '2026-12-02', '2026-12-03');

        $this->assertSame(-2, $available);
    }

    public function test_pedido_atual_nao_conta_contra_si_mesmo(): void
    {
        $item = $this->item(['quantity' => 5]);
        $admin = $this->user(UserRole::Admin);
        $order = $this->order($admin, ['client_name' => 'X', 'start_at' => '2026-12-01', 'end_at' => '2026-12-05']);
        RentalOrderItem::create([
            'rental_order_id' => $order->id, 'rental_item_id' => $item->id,
            'item_name' => $item->name, 'quantity' => 5, 'unit_price' => 10,
        ]);
        $order->update(['status' => RentalOrderStatus::Confirmada->value]);

        $availableExcludingSelf = app(RentalAvailabilityService::class)
            ->availableQuantity($item->id, '2026-12-01', '2026-12-05', $order->id);
        $availableIncludingSelf = app(RentalAvailabilityService::class)
            ->availableQuantity($item->id, '2026-12-01', '2026-12-05');

        $this->assertSame(5, $availableExcludingSelf);
        $this->assertSame(0, $availableIncludingSelf);
    }
}
