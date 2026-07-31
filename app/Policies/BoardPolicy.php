<?php

namespace App\Policies;

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
     * Leitura do quadro. Quem é responsável por algum card daqui entra mesmo sem vínculo em
     * `user_board` (specs/22) — é o que faz o link da notificação abrir o card. Isso **não** coloca
     * o quadro no menu (`BoardController::index` tem consulta própria) nem libera escrita: mover,
     * transferir e editar continuam passando por `canAccessBoard()`.
     */
    public function view(User $user, Board $board): bool
    {
        return $user->canAccessBoard($board) || $user->isAssignedOnBoard($board);
    }

    public function create(User $user): bool
    {
        return $user->isCoordenador();
    }

    public function update(User $user, Board $board): bool
    {
        return $user->isCoordenador();
    }

    public function configure(User $user, Board $board): bool
    {
        return $user->isCoordenador();
    }

    public function delete(User $user, Board $board): bool
    {
        return $user->isCoordenador();
    }
}
