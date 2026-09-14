<?php

namespace App\Observers;

use App\Actions\Finance\SyncCardToFinance;
use App\Actions\Notifications\NotifyCardAssigned;
use App\Models\Board;
use App\Models\Card;
use Illuminate\Validation\ValidationException;

/**
 * Gatilho único das notificações de responsável (specs/22 §4.1) e da sincronia automática com o
 * Financeiro (specs/23 §6.1). O `assignee_id` e o `board_id` são gravados em vários caminhos
 * (CreateCard, UpdateCard, DuplicateCard, TransferCard, ImportTemplate, ...); observar o model
 * cobre todos eles — inclusive os futuros — sem espalhar chamadas pelas Actions.
 */
class CardObserver
{
    public function __construct(
        private NotifyCardAssigned $notify,
        private SyncCardToFinance $syncFinance,
    ) {}

    public function created(Card $card): void
    {
        if ($card->assignee_id) {
            $this->notify->execute($card, $card->assignee_id);
        }

        $this->syncFinanceIfNeeded($card);
    }

    /**
     * Campos que a linha do Financeiro espelha do card (ver SyncCardToFinance::mirrorFromCard):
     * título, fornecedor e os valores previsto/realizado. Mudar qualquer um deles num card já
     * vinculado precisa chegar na planilha sozinho, sem alguém reabrir o modal "Sincronizar".
     */
    private const FINANCE_MIRROR_FIELDS = [
        'title', 'fornecedor_id', 'estimated_value', 'actual_value', 'unit_value',
        'valor_sem_nota', 'valor_com_nota', 'negociado',
    ];

    public function updated(Card $card): void
    {
        // wasChanged() cobre "não tinha responsável e ganhou" e "trocou de responsável".
        if ($card->wasChanged('assignee_id') && $card->assignee_id) {
            $this->notify->execute($card, $card->assignee_id);
        }

        if ($card->wasChanged('board_id')) {
            $this->syncFinanceIfNeeded($card);
        }

        if ($card->wasChanged(self::FINANCE_MIRROR_FIELDS)) {
            $this->resyncFinanceIfLinked($card);
        }
    }

    /**
     * Card que entra num quadro marcado como `feeds_finance` (o quadro "Financeiro") cria/atualiza
     * sozinho a linha de custo do evento — é o gatilho que faz o "chegou no financeiro" acontecer
     * sem ninguém lembrar de clicar.
     *
     * Falha aqui NUNCA derruba a movimentação do card: sem evento vinculado ou com a prestação de
     * contas fechada, o envio simplesmente não acontece e o botão manual continua disponível.
     */
    private function syncFinanceIfNeeded(Card $card): void
    {
        if (! $card->event_id || ! Board::whereKey($card->board_id)->value('feeds_finance')) {
            return;
        }

        try {
            $this->syncFinance->execute($card, auth()->user());
        } catch (ValidationException) {
            // Silencioso de propósito — ver docblock.
        }
    }

    /**
     * Card sem linha no Financeiro não faz nada aqui: essa edição some quando a pessoa clicar em
     * "Enviar para o Financeiro" pela primeira vez, com os valores já atualizados. Só reagimos
     * quando JÁ existe uma linha (`card_id` vinculado), para não criar uma linha nova por engano
     * a partir de uma edição qualquer no card.
     */
    private function resyncFinanceIfLinked(Card $card): void
    {
        if (! $card->financeCostItem()->exists()) {
            return;
        }

        try {
            $this->syncFinance->execute($card, auth()->user());
        } catch (ValidationException) {
            // Silencioso — mesma razão do docblock de syncFinanceIfNeeded().
        }
    }
}
