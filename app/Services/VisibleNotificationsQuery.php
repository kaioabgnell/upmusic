<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\Builder;

/**
 * Escopo de visibilidade das notificações (specs/22 §4.2). Usado pelos três endpoints — lista,
 * contador e "marcar todas como lidas" — para que o badge conte exatamente o que a lista mostra
 * e "marcar todas" limpe exatamente aquilo.
 *
 * Só aparece notificação cujo card ainda existe (não soft-deleted) e que o usuário ainda consegue
 * abrir. Sem isso o sino viraria fonte de link morto e de 403.
 */
class VisibleNotificationsQuery
{
    /**
     * O serviço é deliberadamente SEM ESTADO. Uma versão anterior memoizava os quadros do usuário
     * em uma propriedade e, como o container reaproveita a instância, o escopo de um usuário
     * vazava para o request seguinte de outro usuário. Nada aqui pode depender de chamada anterior.
     */
    public function for(User $user): Builder
    {
        return UserNotification::query()
            ->where('user_notifications.user_id', $user->id)
            ->whereHas('card', function ($query) use ($user) {
                // Escopo por evento do coordenador restrito (specs/20).
                $query->visibleTo($user);

                // Admin e Coordenador acessam todos os quadros (User::canAccessBoard()), então a
                // restrição por quadro só vale para o papel `usuario`. Subquery em vez de pluck()
                // para não gastar um round trip extra a cada chamada.
                //
                // `orWhere(assignee_id)`: ser o responsável atual basta, mesmo sem acesso ao quadro
                // — é o que faz a notificação chegar a quem foi colocado como responsável em um
                // card de outro departamento (CardPolicy::view segue a mesma régua, então o link
                // abre). Se o responsável for trocado depois, a notificação some da lista de quem
                // deixou de ser responsável — coerente com "só aparece o que ainda dá para abrir".
                if (! $user->isAdmin() && ! $user->isCoordenador()) {
                    $query->where(fn ($q) => $q
                        ->whereIn('board_id', $user->boards()->select('boards.id'))
                        ->orWhere('assignee_id', $user->id));
                }
            });
    }
}
