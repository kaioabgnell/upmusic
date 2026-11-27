<?php

namespace App\Policies;

use App\Domain\Enums\AccessModule;
use App\Models\Board;
use App\Models\User;

class BoardPolicy
{
    // Admin liberado via Gate::before.

    public function viewAny(User $user): bool
    {
        return true; // todos veem a lista (filtrada por acesso no controller)
    }

    /**
     * Leitura do quadro. Quem é responsável por algum card daqui entra mesmo sem o quadro no perfil
     * (specs/22) — é o que faz o link da notificação abrir o card. Isso **não** coloca o quadro no
     * menu (`Board::visibleTo()`) nem libera escrita: mover, transferir e editar continuam passando
     * por `canAccessBoard()`.
     */
    public function view(User $user, Board $board): bool
    {
        // Quadro de custos fixos (specs/27 §1.2) não abre por responsabilidade de card.
        return $user->canAccessBoard($board) || (! $board->fixed_costs && $user->isAssignedOnBoard($board));
    }

    /** Estrutura dos quadros: módulo "Configurar quadros" (specs/28 §3). */
    public function create(User $user): bool
    {
        return $user->hasModule(AccessModule::BoardsConfigure);
    }

    public function update(User $user, Board $board): bool
    {
        return $this->configure($user, $board);
    }

    /**
     * "Configurar quadros" + acesso ao quadro: o quadro de custos fixos só se configura com o módulo
     * Custos fixos também (specs/28 §10).
     */
    public function configure(User $user, Board $board): bool
    {
        return $user->hasModule(AccessModule::BoardsConfigure) && $user->canAccessBoard($board);
    }

    public function delete(User $user, Board $board): bool
    {
        return $this->configure($user, $board);
    }
}
