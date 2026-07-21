import cardPanel from './card-panel';

/**
 * Componente Alpine do quadro Kanban do upMusic.
 * `columns` é o único estado reativo (fonte da verdade): colunas + cards são buscados de forma
 * assíncrona (GET .../kanban) depois que o shell da página já está na tela, e toda mutação
 * (criar/editar/mover/excluir/concluir/transferir/drag-and-drop) atualiza esse array — sem reload.
 * Cards são renderizados no cliente (Alpine x-for), tanto no Kanban quanto na Lista.
 *
 * O modal de detalhe/criação de card (`boards/partials/card-panel.blade.php`) é compartilhado com
 * a listagem global "Todos os cards" via `card-panel.js` — aqui só ficam os `afterCard*` hooks que
 * mantêm `columns` sincronizado sem precisar recarregar a página.
 */
export default function kanban(config) {
    return cardPanel({
        cfg: config,
        csrf: document.querySelector('meta[name="csrf-token"]').content,

        viewMode: 'kanban', // 'kanban' | 'lista'

        // ---- Estado reativo do quadro (colunas + cards) -----------------------
        columns: [],
        loadingCards: true,
        loadError: false,
        sortables: [],

        search: '',
        filters: { empresa_id: '', event_id: '', assignee_id: '', priority: '' },

        tooltip: { show: false, text: '', x: 0, y: 0 },

        init() {
            this.search = this.cfg.initialFilters.search || '';
            this.filters = {
                empresa_id: this.cfg.initialFilters.empresa_id || '',
                event_id: this.cfg.initialFilters.event_id || '',
                assignee_id: this.cfg.initialFilters.assignee_id || '',
                priority: this.cfg.initialFilters.priority || '',
            };
            // Shell inicial: colunas já vêm do servidor (sem cards); os cards chegam no fetchCards().
            this.columns = this.cfg.columns.map((c) => ({ ...c, cards: [] }));
            this.fetchCards().then(() => this.openCardFromQueryString());
        },

        // Após criar um card fora do Kanban (ex.: Captura Rápida), o redirect inclui ?abrir_card=ID
        // para já abrir o modal de detalhe assim que os cards carregarem (specs/16).
        openCardFromQueryString() {
            const id = new URLSearchParams(window.location.search).get('abrir_card');
            if (!id) return;
            this.openCard(Number(id));
            const url = new URL(window.location.href);
            url.searchParams.delete('abrir_card');
            window.history.replaceState({}, '', url);
        },

        // ---- Tooltip (estilo Bootstrap, escapa containers com scroll) --------
        showTooltip(event) {
            const text = event.currentTarget.dataset.tooltip;
            if (!text) return;
            const rect = event.currentTarget.getBoundingClientRect();
            this.tooltip = { show: true, text, x: rect.left + rect.width / 2, y: rect.top - 8 };
        },

        hideTooltip() {
            this.tooltip.show = false;
        },

        // ---- Carregamento assíncrono dos cards --------------------------------
        filterQueryString() {
            const params = new URLSearchParams();
            if (this.search) params.set('search', this.search);
            if (this.filters.empresa_id) params.set('empresa_id', this.filters.empresa_id);
            if (this.filters.event_id) params.set('event_id', this.filters.event_id);
            if (this.filters.assignee_id) params.set('assignee_id', this.filters.assignee_id);
            if (this.filters.priority) params.set('priority', this.filters.priority);
            return params.toString();
        },

        async fetchCards() {
            this.loadingCards = true;
            this.loadError = false;
            try {
                const qs = this.filterQueryString();
                const data = await this.api(`${this.cfg.urls.kanbanData}${qs ? `?${qs}` : ''}`);
                this.columns = data.columns;
                this.$nextTick(() => this.initSortable());
            } catch (e) {
                this.loadError = true;
            } finally {
                this.loadingCards = false;
            }
        },

        get activeFilterCount() {
            return ['empresa_id', 'event_id', 'assignee_id', 'priority'].filter((k) => this.filters[k]).length;
        },

        reloadCards() {
            this.updateUrlQueryString();
            this.fetchCards();
        },

        updateUrlQueryString() {
            const qs = this.filterQueryString();
            const url = qs ? `${window.location.pathname}?${qs}` : window.location.pathname;
            window.history.replaceState({}, '', url);
        },

        clearFilters() {
            this.search = '';
            this.filters = { empresa_id: '', event_id: '', assignee_id: '', priority: '' };
            this.reloadCards();
        },

        // ---- Visão Lista (achatada a partir de `columns`) ---------------------
        get flatCards() {
            return this.columns.flatMap((col) => col.cards.map((card) => ({
                ...card,
                column_name: col.name,
                column_color: col.color,
            })));
        },

        // ---- Helpers de renderização (equivalentes ao que era feito em Blade) --
        dueBorderClass(card) {
            return { today: 'border-2 border-red-500 due-today-pulse', tomorrow: 'border-2 border-brand-orange' }[card.due_status]
                || 'border border-hairline';
        },

        dueTooltipText(card) {
            return { overdue: 'Vencido', today: 'O prazo vence hoje', tomorrow: 'O prazo vence amanhã' }[card.due_status] || '';
        },

        // Classe do texto da data no card: vermelho/negrito quando o prazo já passou (vencido).
        dueDateClass(card) {
            return card.due_status === 'overdue' ? 'text-red-600 font-semibold' : '';
        },

        dueBadgeMeta(card) {
            const map = {
                overdue: { variant: 'danger', label: 'Vencido' },
                today: { variant: 'danger', label: 'Vence hoje' },
                tomorrow: { variant: 'orange', label: 'Vence amanhã' },
            };
            return map[card.due_status] || null;
        },

        badgeClasses(variant) {
            const variants = {
                neutral: 'bg-gray-100 text-gray-700',
                orange: 'bg-brand-orange/15 text-brand-orange-deep',
                success: 'bg-green-100 text-green-700',
                danger: 'bg-red-100 text-red-700',
                dark: 'bg-brand-ink text-white',
            };
            return `inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-medium ${variants[variant] || variants.neutral}`;
        },

        truncate(str, len) {
            if (!str) return '';
            return str.length > len ? `${str.slice(0, len)}…` : str;
        },

        formatMoneyBR(value) {
            if (value === null || value === undefined || value === '') return '';
            const num = Number(value);
            return Number.isNaN(num) ? '' : num.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        // ---- Mutações do array reativo (usadas pelos hooks afterCard*) --------
        upsertCardInColumns(card) {
            let removedFromCol = null;
            let removedFromIndex = null;
            this.columns.forEach((col) => {
                const idx = col.cards.findIndex((c) => c.id === card.id);
                if (idx !== -1) {
                    col.cards.splice(idx, 1);
                    removedFromCol = col;
                    removedFromIndex = idx;
                }
            });
            const targetCol = this.columns.find((c) => c.id === card.board_column_id);
            if (!targetCol) return;
            if (removedFromCol === targetCol && removedFromIndex !== null) {
                targetCol.cards.splice(removedFromIndex, 0, card);
            } else {
                targetCol.cards.push(card);
            }
        },

        removeCardFromColumns(cardId) {
            this.columns.forEach((col) => {
                const idx = col.cards.findIndex((c) => c.id === cardId);
                if (idx !== -1) col.cards.splice(idx, 1);
            });
        },

        bumpCardCount(cardId, field, delta) {
            for (const col of this.columns) {
                const card = col.cards.find((c) => c.id === cardId);
                if (card) {
                    card[field] = (card[field] || 0) + delta;
                    return;
                }
            }
        },

        // ---- Hooks do card-panel.js compartilhado -----------------------------
        afterCardSaved(card) {
            this.upsertCardInColumns(card);
        },

        afterCardRemoved(cardId) {
            this.removeCardFromColumns(cardId);
        },

        afterCardMoved(fromColumnId, toColumnId, cardId) {
            const fromCol = this.columns.find((c) => c.id === fromColumnId);
            const toCol = this.columns.find((c) => c.id === toColumnId);
            if (fromCol && toCol) {
                const idx = fromCol.cards.findIndex((c) => c.id === cardId);
                if (idx !== -1) {
                    const [card] = fromCol.cards.splice(idx, 1);
                    card.board_column_id = toColumnId;
                    toCol.cards.push(card);
                }
            }
        },

        afterCardTransferred() {
            this.removeCardFromColumns(this.cardId);
        },

        afterCardConcluded() {
            this.removeCardFromColumns(this.cardId);
        },

        afterCardArchived() {
            this.removeCardFromColumns(this.cardId);
        },

        afterCardDuplicated(card) {
            this.upsertCardInColumns(card);
        },

        afterCardApproved(card) {
            this.upsertCardInColumns(card);
        },

        afterCardRejected() {
            this.removeCardFromColumns(this.cardId);
        },

        // ---- Drag and drop ---------------------------------------------------
        initSortable() {
            this.destroySortable();
            this.$el.querySelectorAll('[data-column-id] .kanban-cards').forEach((list) => {
                this.sortables.push(new window.Sortable(list, {
                    group: 'kanban',
                    animation: 150,
                    ghostClass: 'opacity-40',
                    draggable: '.kanban-card',
                    onEnd: (evt) => this.persistMove(evt),
                }));
            });
        },

        destroySortable() {
            this.sortables.forEach((s) => s.destroy());
            this.sortables = [];
        },

        async persistMove(evt) {
            const cardId = Number(evt.item.dataset.cardId);
            const fromColumnId = Number(evt.from.closest('[data-column-id]').dataset.columnId);
            const toColumnId = Number(evt.to.closest('[data-column-id]').dataset.columnId);
            const newIndex = evt.newIndex;
            const oldIndex = evt.oldIndex;

            // O Sortable já moveu o nó no DOM durante o drag; desfazemos essa mutação manual e
            // deixamos o Alpine (x-for, dono do DOM via `columns`) redesenhar a partir do array —
            // evita nó duplicado/fantasma na disputa de controle do DOM entre os dois.
            evt.item.remove();
            evt.from.insertBefore(evt.item, evt.from.children[oldIndex] || null);

            const fromCol = this.columns.find((c) => c.id === fromColumnId);
            const toCol = this.columns.find((c) => c.id === toColumnId);
            const cardIndex = fromCol.cards.findIndex((c) => c.id === cardId);
            const [card] = fromCol.cards.splice(cardIndex, 1);
            card.board_column_id = toColumnId;
            toCol.cards.splice(newIndex, 0, card);

            try {
                await this.api(this.cardUrl(cardId, '/mover'), 'POST', {
                    board_column_id: toColumnId,
                    position: newIndex,
                });
                // window.upAlerts.notifySuccess('Card movido.');
            } catch (e) {
                const revertIndex = toCol.cards.findIndex((c) => c.id === cardId);
                const [reverted] = toCol.cards.splice(revertIndex, 1);
                reverted.board_column_id = fromColumnId;
                fromCol.cards.splice(cardIndex, 0, reverted);
                window.upAlerts.notifyError(e.message);
            }
        },
    });
}
