<?php

namespace App\Observers;

use App\Actions\Notifications\NotifyCardAssigned;
use App\Models\Card;

/**
 * Gatilho único das notificações de responsável (specs/22 §4.1). O `assignee_id` é gravado em
 * vários caminhos (CreateCard, UpdateCard, DuplicateCard, ImportTemplate, ...); observar o model
 * cobre todos eles — inclusive os futuros — sem espalhar chamadas pelas Actions.
 */
class CardObserver
{
    public function __construct(private NotifyCardAssigned $notify) {}

    public function created(Card $card): void
    {
        if ($card->assignee_id) {
            $this->notify->execute($card, $card->assignee_id);
        }
    }

    public function updated(Card $card): void
    {
        // wasChanged() cobre "não tinha responsável e ganhou" e "trocou de responsável".
        if ($card->wasChanged('assignee_id') && $card->assignee_id) {
            $this->notify->execute($card, $card->assignee_id);
        }
    }
}
