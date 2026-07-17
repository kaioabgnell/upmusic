<?php

namespace App\Policies;

use App\Models\Board;
use App\Models\Card;
use App\Models\User;

class CardPolicy
{
    // Admin liberado via Gate::before.

    public function view(User $user, Card $card): bool
    {
        return $user->canAccessBoard($card->board);
    }

    public function create(User $user, Board $board): bool
    {
        return $user->canAccessBoard($board);
    }

    public function update(User $user, Card $card): bool
    {
        return $user->canAccessBoard($card->board);
    }

    public function delete(User $user, Card $card): bool
    {
        return $user->canAccessBoard($card->board);
    }
}
