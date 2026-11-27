<?php

namespace App\Actions\Events;

use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Cria um evento. Quem tem "Eventos visíveis: somente os selecionados" ganha o evento novo na hora
 * nos eventos visíveis dele — senão o evento sumiria da lista logo depois de criado (specs/28 §6).
 */
class CreateEvent
{
    public function execute(array $data, User $actor): Event
    {
        return DB::transaction(function () use ($data, $actor) {
            $event = Event::create($data);

            if ($actor->isEventScoped()) {
                $actor->events()->attach($event->id);
                $actor->flushAccessCache();
            }

            return $event;
        });
    }
}
