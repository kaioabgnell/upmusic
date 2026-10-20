<?php

namespace App\Actions\Rentals;

use App\Domain\Enums\RentalItemDeletionReason;
use App\Domain\Enums\RentalOrderStatus;
use App\Models\RentalItem;
use App\Models\RentalOrderItem;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Exclusão lógica de um item do inventário (specs/24 §4.1 / §2 Q11-Q22): motivo obrigatório, e
 * recusada se o item estiver reservado num pedido Confirmada/Em uso ainda não encerrado.
 */
class DeleteRentalItem
{
    /** @param  array{deletion_reason: string, deletion_reason_other?: string}  $data */
    public function execute(RentalItem $item, array $data, User $actor): RentalItem
    {
        $blocking = RentalOrderItem::query()
            ->where('rental_item_id', $item->id)
            ->whereHas('order', fn ($q) => $q
                ->whereIn('status', [RentalOrderStatus::Confirmada->value, RentalOrderStatus::EmUso->value])
                ->where(fn ($q) => $q->whereNull('end_at')->orWhere('end_at', '>=', now())))
            ->with('order:id,code')
            ->get();

        if ($blocking->isNotEmpty()) {
            $codes = $blocking->pluck('order.code')->filter()->unique()->implode(', ');
            throw ValidationException::withMessages([
                'rental_item' => "Este item está reservado em pedido(s) ativo(s): {$codes}.",
            ]);
        }

        $reason = RentalItemDeletionReason::from($data['deletion_reason']);

        $item->update([
            'deletion_reason' => $reason->value,
            'deletion_reason_other' => $reason === RentalItemDeletionReason::Outros ? $data['deletion_reason_other'] : null,
            'deleted_by' => $actor->id,
            'active' => false,
        ]);
        $item->delete();

        return $item;
    }
}
