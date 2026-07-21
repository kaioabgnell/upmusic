import cardPanel from './card-panel';

/**
 * Componente Alpine da listagem global "Todos os cards" (todos os quadros, todos os status).
 * O modal de detalhe/edição é o mesmo do Kanban (`boards/partials/card-panel.blade.php` +
 * `card-panel.js`) — a diferença é que aqui cada card pode ser de um quadro diferente, então
 * `afterCardOpened` busca sob demanda os campos/colunas/quadros de destino daquele card
 * específico (no Kanban isso já vem fixo no `cfg` da página, que é de um único quadro).
 * Como a listagem em si é uma tabela paginada renderizada no servidor, qualquer mutação
 * (salvar/mover/excluir/transferir/concluir/reabrir) simplesmente recarrega a página.
 */
export default function cardsHub(config) {
    return cardPanel({
        cfg: config,
        csrf: document.querySelector('meta[name="csrf-token"]').content,

        columns: [],
        boardColumnsCache: {},

        async deleteCard(id, title) {
            if (!(await window.upAlerts.confirmAction({ text: `Excluir o card "${title}"?` }))) return;
            try {
                await this.api(this.cardUrl(id), 'DELETE');
                window.upAlerts.notifySuccess('Card excluído.');
                window.location.reload();
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        // ---- Hooks do card-panel.js compartilhado -----------------------------
        async afterCardOpened(c) {
            this.cfg.transferBoards = (this.cfg.boards || []).filter((b) => b.id !== c.board_id);
            if (!this.boardColumnsCache[c.board_id]) {
                const data = await this.api(`${this.cfg.urls.boardKanbanData}/${c.board_id}/kanban`);
                this.boardColumnsCache[c.board_id] = data.columns;
            }
            this.columns = this.boardColumnsCache[c.board_id];
        },

        afterCardSaved() {
            window.location.reload();
        },

        afterCardRemoved() {
            window.location.reload();
        },

        afterCardMoved() {
            window.location.reload();
        },

        afterCardTransferred() {
            window.location.reload();
        },

        afterCardConcluded() {
            window.location.reload();
        },

        afterCardReopened() {
            window.location.reload();
        },

        afterCardDuplicated() {
            window.location.reload();
        },

        afterCardArchived() {
            window.location.reload();
        },

        afterCardUnarchived() {
            window.location.reload();
        },

        afterCardApproved() {
            window.location.reload();
        },

        afterCardRejected() {
            window.location.reload();
        },
    });
}
