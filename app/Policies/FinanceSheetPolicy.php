<?php

namespace App\Policies;

use App\Domain\Enums\AccessModule;
use App\Models\Event;
use App\Models\FinanceSheet;
use App\Models\User;

/**
 * Acesso ao Financeiro do Evento (specs/23 §11).
 *
 * Admin é liberado antes daqui pelo `Gate::before`. Demais: módulo `finance` do perfil de acesso
 * (specs/28) + "Eventos visíveis". Quem não tem o módulo participa pelo Kanban (sobe o anexo no card
 * e usa o botão "Enviar para o Financeiro", autorizado pela CardPolicy, não por esta).
 */
class FinanceSheetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasModule(AccessModule::Finance);
    }

    public function view(User $user, FinanceSheet $sheet): bool
    {
        return $user->hasModule(AccessModule::Finance) && $this->withinScope($user, $sheet->event_id);
    }

    /**
     * Editar linhas, receitas, pagamentos e documentos.
     *
     * Não checa `isClosed()` de propósito: `Gate::before` libera o Admin em qualquer policy, então
     * a trava da prestação de contas fechada não sobreviveria aqui. Ela é aplicada em
     * `FinanceController::authorizeWrite()`, que responde 422 para qualquer papel.
     */
    public function update(User $user, FinanceSheet $sheet): bool
    {
        return $this->view($user, $sheet);
    }

    public function close(User $user, FinanceSheet $sheet): bool
    {
        return $this->view($user, $sheet);
    }

    /** Reabrir é exclusivo do Admin (liberado pelo Gate::before). */
    public function reopen(User $user, FinanceSheet $sheet): bool
    {
        return false;
    }

    /** Quem é restrito por evento (specs/28 §6) só enxerga as planilhas dos eventos dele. */
    public function viewEvent(User $user, Event $event): bool
    {
        return $user->hasModule(AccessModule::Finance) && $this->withinScope($user, $event->id);
    }

    private function withinScope(User $user, ?int $eventId): bool
    {
        $ids = $user->allowedEventIds();

        return $ids === null || ($eventId !== null && $ids->contains($eventId));
    }
}
