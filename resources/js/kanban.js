/**
 * Componente Alpine do quadro Kanban do upMusic.
 * `columns` é o único estado reativo (fonte da verdade): colunas + cards são buscados de forma
 * assíncrona (GET .../kanban) depois que o shell da página já está na tela, e toda mutação
 * (criar/editar/mover/excluir/concluir/transferir/drag-and-drop) atualiza esse array — sem reload.
 * Cards são renderizados no cliente (Alpine x-for), tanto no Kanban quanto na Lista.
 */
export default function kanban(config) {
    return {
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

        panelOpen: false,
        mode: 'view', // 'view' | 'create'
        loading: false,
        saving: false,
        cardId: null,
        columnId: null,
        tab: 'detalhes', // 'detalhes' | 'comentarios' | 'historico'

        form: {},
        errors: {},
        comments: [],
        attachments: [],
        movements: [],
        newComment: '',
        uploadKind: 'geral',

        transferOpen: false,
        transferBoardId: '',

        assigneeOpen: false,
        assigneeSearch: '',
        dueOpen: false,
        priorityOpen: false,
        fornecedorOpen: false,
        fornecedorSearch: '',

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
            this.fetchCards();
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

        // ---- Helpers de rede -------------------------------------------------
        async api(url, method = 'GET', body = null) {
            const res = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrf,
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body ? JSON.stringify(body) : null,
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                const err = new Error(data.message || 'Erro na operação.');
                err.errors = data.errors || {};
                err.status = res.status;
                throw err;
            }
            return data;
        },

        cardUrl(id, suffix = '') {
            return `${this.cfg.urls.cardBase}/${id}${suffix}`;
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
            return { today: 'O prazo vence hoje', tomorrow: 'O prazo vence amanhã' }[card.due_status] || '';
        },

        dueBadgeMeta(card) {
            const map = {
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

        // ---- Mutações do array reativo (usadas pelas ações abaixo) ------------
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
                window.upAlerts.notifySuccess('Card movido.');
            } catch (e) {
                const revertIndex = toCol.cards.findIndex((c) => c.id === cardId);
                const [reverted] = toCol.cards.splice(revertIndex, 1);
                reverted.board_column_id = fromColumnId;
                fromCol.cards.splice(cardIndex, 0, reverted);
                window.upAlerts.notifyError(e.message);
            }
        },

        // ---- Painel: criar / abrir ------------------------------------------
        blankForm(columnId) {
            const fields = {};
            this.cfg.fields.forEach((f) => (fields[f.id] = f.type === 'checkbox' ? false : ''));
            return {
                title: '', description: '', empresa_id: '', fornecedor_id: '', event_id: '', assignee_id: '',
                due_date: '', priority: 'media', estimated_value: '', actual_value: '',
                board_column_id: columnId, fields,
            };
        },

        openCreate(columnId) {
            this.mode = 'create';
            this.cardId = null;
            this.columnId = columnId;
            this.form = this.blankForm(columnId);
            this.errors = {};
            this.comments = []; this.attachments = []; this.movements = [];
            this.transferOpen = false;
            this.assigneeOpen = false; this.assigneeSearch = ''; this.dueOpen = false; this.priorityOpen = false;
            this.fornecedorOpen = false; this.fornecedorSearch = '';
            this.tab = 'detalhes';
            this.panelOpen = true;
        },

        async openCard(id) {
            this.mode = 'view';
            this.panelOpen = true;
            this.loading = true;
            this.errors = {};
            this.transferOpen = false;
            this.assigneeOpen = false; this.assigneeSearch = ''; this.dueOpen = false; this.priorityOpen = false;
            this.fornecedorOpen = false; this.fornecedorSearch = '';
            this.tab = 'detalhes';
            try {
                const c = await this.api(this.cardUrl(id));
                this.cardId = c.id;
                this.columnId = c.board_column_id;
                const fields = {};
                this.cfg.fields.forEach((f) => {
                    const v = c.field_values[f.id];
                    fields[f.id] = f.type === 'checkbox' ? v === '1' || v === true : (v ?? '');
                });
                this.form = {
                    title: c.title, description: c.description ?? '',
                    empresa_id: c.empresa_id ?? '', fornecedor_id: c.fornecedor_id ?? '', event_id: c.event_id ?? '', assignee_id: c.assignee_id ?? '',
                    due_date: c.due_date ?? '', priority: c.priority,
                    estimated_value: this.moneyFromDecimal(c.estimated_value),
                    actual_value: this.moneyFromDecimal(c.actual_value),
                    board_column_id: c.board_column_id, fields,
                };
                this.comments = c.comments;
                this.attachments = c.attachments;
                this.movements = c.movements;
            } catch (e) {
                window.upAlerts.notifyError(e.message);
                this.panelOpen = false;
            } finally {
                this.loading = false;
            }
        },

        closePanel() {
            this.panelOpen = false;
        },

        get isFinalColumn() {
            const col = this.columns.find((c) => c.id === Number(this.columnId));
            return col ? col.is_final : false;
        },

        get currentColumnIndex() {
            return this.columns.findIndex((c) => c.id === Number(this.columnId));
        },

        get previousColumns() {
            const i = this.currentColumnIndex;
            return i > 0 ? this.columns.slice(0, i) : [];
        },

        get nextColumns() {
            const i = this.currentColumnIndex;
            return i >= 0 ? this.columns.slice(i + 1) : [];
        },

        async moveToColumn(columnId) {
            try {
                await this.api(this.cardUrl(this.cardId, '/mover'), 'POST', {
                    board_column_id: Number(columnId),
                    position: 9999,
                });
                const fromCol = this.columns.find((c) => c.id === Number(this.columnId));
                const toCol = this.columns.find((c) => c.id === Number(columnId));
                if (fromCol && toCol) {
                    const idx = fromCol.cards.findIndex((c) => c.id === this.cardId);
                    if (idx !== -1) {
                        const [card] = fromCol.cards.splice(idx, 1);
                        card.board_column_id = Number(columnId);
                        toCol.cards.push(card);
                    }
                }
                window.upAlerts.notifySuccess('Card movido.');
                this.closePanel();
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        columnPillStyle(color) {
            const c = color || '#8a8a8a';
            return `background: ${c}1f; color: ${c};`;
        },

        // ---- Ações rápidas: responsável / vencimento / prioridade -----------
        get selectedAssignee() {
            return this.cfg.assignees.find((u) => u.id === Number(this.form.assignee_id)) || null;
        },

        get filteredAssignees() {
            const q = this.assigneeSearch.trim().toLowerCase();
            if (!q) return this.cfg.assignees;
            return this.cfg.assignees.filter((u) => u.name.toLowerCase().includes(q));
        },

        // ---- Fornecedor (select pesquisável + cadastro rápido) ---------------
        get selectedFornecedor() {
            return this.cfg.fornecedores.find((f) => f.id === Number(this.form.fornecedor_id)) || null;
        },

        get filteredFornecedores() {
            const q = this.fornecedorSearch.trim().toLowerCase();
            if (!q) return this.cfg.fornecedores;
            return this.cfg.fornecedores.filter((f) => f.name.toLowerCase().includes(q) || f.document.includes(q));
        },

        initialsOf(name) {
            if (!name) return '';
            const parts = name.trim().split(/\s+/);
            const first = parts[0].charAt(0).toUpperCase();
            return parts.length > 1 ? first + parts[parts.length - 1].charAt(0).toUpperCase() : first;
        },

        formatDateBR(dateStr) {
            if (!dateStr) return '';
            const [y, m, d] = dateStr.split('-');
            return `${d}/${m}/${y}`;
        },

        priorityMeta(p) {
            const map = {
                baixa: { label: 'Baixa', classes: 'bg-gray-100 text-gray-600 border-gray-200', dotClass: 'bg-gray-400' },
                media: { label: 'Média', classes: 'bg-brand-orange/15 text-brand-orange-deep border-brand-orange/30', dotClass: 'bg-brand-orange' },
                alta: { label: 'Alta', classes: 'bg-red-100 text-red-700 border-red-200', dotClass: 'bg-red-600' },
            };
            return map[p] || map.media;
        },

        // ---- Salvar ----------------------------------------------------------
        async save() {
            this.saving = true;
            this.errors = {};
            try {
                let card;
                if (this.mode === 'create') {
                    card = await this.api(this.cfg.urls.cardStore, 'POST', this.form);
                } else {
                    card = await this.api(this.cardUrl(this.cardId), 'PUT', this.form);
                }
                this.upsertCardInColumns(card);
                window.upAlerts.notifySuccess(this.mode === 'create' ? 'Card criado.' : 'Card atualizado.');
                this.closePanel();
            } catch (e) {
                this.errors = e.errors || {};
                window.upAlerts.notifyError(e.message);
            } finally {
                this.saving = false;
            }
        },

        async remove() {
            if (!(await window.upAlerts.confirmAction({ text: 'Excluir este card?' }))) return;
            try {
                await this.api(this.cardUrl(this.cardId), 'DELETE');
                this.removeCardFromColumns(this.cardId);
                window.upAlerts.notifySuccess('Card excluído.');
                this.closePanel();
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        // ---- Comentários -----------------------------------------------------
        async addComment() {
            if (!this.newComment.trim()) return;
            try {
                const c = await this.api(this.cardUrl(this.cardId, '/comentarios'), 'POST', { body: this.newComment });
                this.comments.unshift(c);
                this.newComment = '';
                this.bumpCardCount(this.cardId, 'comments_count', 1);
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        // ---- Anexos ----------------------------------------------------------
        async uploadAttachment(event) {
            const file = event.target.files[0];
            if (!file) return;
            const fd = new FormData();
            fd.append('file', file);
            fd.append('kind', this.uploadKind);
            try {
                const res = await fetch(this.cardUrl(this.cardId, '/anexos'), {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf, Accept: 'application/json' },
                    body: fd,
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'Falha no upload.');
                this.attachments.push(data);
                event.target.value = '';
                this.bumpCardCount(this.cardId, 'attachments_count', 1);
                window.upAlerts.notifySuccess('Anexo enviado.');
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        async deleteAttachment(att) {
            if (!(await window.upAlerts.confirmAction({ text: `Excluir "${att.original_name}"?` }))) return;
            try {
                await this.api(`${this.cfg.urls.anexoBase}/${att.id}`, 'DELETE');
                this.attachments = this.attachments.filter((a) => a.id !== att.id);
                this.bumpCardCount(this.cardId, 'attachments_count', -1);
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        // ---- Transferência de departamento -----------------------------------
        async doTransfer() {
            if (!this.transferBoardId) return;
            try {
                const r = await this.api(this.cardUrl(this.cardId, '/enviar-departamento'), 'POST', {
                    board_id: Number(this.transferBoardId),
                });
                this.removeCardFromColumns(this.cardId);
                window.upAlerts.notifySuccess(r.message || 'Card transferido.');
                this.closePanel();
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        async doConclude() {
            const confirmed = await window.upAlerts.confirmAction({
                title: 'Concluir card?',
                text: 'O card deixará de aparecer em qualquer quadro. Você poderá reabri-lo depois em "Todos os cards".',
                confirmText: 'Concluir',
                icon: 'question',
            });
            if (!confirmed) return;
            try {
                const r = await this.api(this.cardUrl(this.cardId, '/concluir'), 'POST');
                this.removeCardFromColumns(this.cardId);
                window.upAlerts.notifySuccess(r.message || 'Card concluído.');
                this.closePanel();
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        // ---- Máscara de dinheiro (R$) -----------------------------------------
        // Formata o valor vindo do banco (ex.: "1500.00") para o padrão BR ("1.500,00")
        // ao abrir um card existente. A digitação em si é mascarada pelo plugin
        // @alpinejs/mask (x-mask:dynamic="$money($input, ',')" no card-panel).
        moneyFromDecimal(value) {
            if (value === null || value === undefined || value === '') return '';
            const num = Number(value);
            return Number.isNaN(num) ? '' : num.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        // ---- Cadastro rápido de empresa -------------------------------------
        formatDocument(value, type) {
            const digits = value.replace(/\D/g, '');
            if (type === 'PF') {
                return digits
                    .replace(/(\d{3})(\d)/, '$1.$2')
                    .replace(/(\d{3})(\d)/, '$1.$2')
                    .replace(/(\d{3})(\d{1,2})$/, '$1-$2')
                    .slice(0, 14);
            }
            return digits
                .replace(/(\d{2})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d)/, '$1/$2')
                .replace(/(\d{4})(\d{1,2})$/, '$1-$2')
                .slice(0, 18);
        },
        async quickEmpresa() {
            const inputClass = 'block w-full h-9 text-sm border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md mb-2';
            const { value: form } = await window.Swal.fire({
                title: 'Nova empresa',
                customClass: { title: 'up-modal-title' },
                html:
                    '<div class="text-left">' +
                        `<select id="qe-type" class="${inputClass}">` +
                            '<option value="PJ">Pessoa Jurídica</option>' +
                            '<option value="PF">Pessoa Física</option>' +
                        '</select>' +
                        `<input id="qe-name" class="${inputClass}" placeholder="Razão social">` +
                        `<input id="qe-document" class="${inputClass.replace(' mb-2', '')}" placeholder="CNPJ" maxlength="18">` +
                    '</div>',
                focusConfirm: false,
                showCancelButton: true,
                confirmButtonText: 'Cadastrar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#ff8c1e',
                didOpen: () => {
                    const typeEl = document.getElementById('qe-type');
                    const nameEl = document.getElementById('qe-name');
                    const docEl = document.getElementById('qe-document');
                    const applyType = () => {
                        nameEl.placeholder = typeEl.value === 'PF' ? 'Nome completo' : 'Razão social';
                        docEl.placeholder = typeEl.value === 'PF' ? 'CPF' : 'CNPJ';
                        docEl.value = this.formatDocument(docEl.value, typeEl.value);
                    };
                    typeEl.addEventListener('change', applyType);
                    docEl.addEventListener('input', () => { docEl.value = this.formatDocument(docEl.value, typeEl.value); });
                    applyType();
                },
                preConfirm: () => ({
                    corporate_name: document.getElementById('qe-name').value,
                    type: document.getElementById('qe-type').value,
                    document: document.getElementById('qe-document').value,
                }),
            });
            if (!form) return;
            try {
                const res = await fetch(this.cfg.urls.empresaQuick, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf, Accept: 'application/json' },
                    body: JSON.stringify(form),
                });
                const data = await res.json();
                if (!res.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || 'Erro ao cadastrar.');
                this.cfg.empresas.push({ id: data.id, corporate_name: data.text });
                this.form.empresa_id = data.id;
                window.upAlerts.notifySuccess('Empresa cadastrada.');
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        async quickFornecedor() {
            const inputClass = 'block w-full h-9 text-sm border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md mb-2';
            const { value: form } = await window.Swal.fire({
                title: 'Novo fornecedor',
                customClass: { title: 'up-modal-title' },
                html:
                    '<div class="text-left">' +
                        `<select id="qf-type" class="${inputClass}">` +
                            '<option value="PJ">Pessoa Jurídica</option>' +
                            '<option value="PF">Pessoa Física</option>' +
                        '</select>' +
                        `<input id="qf-name" class="${inputClass}" placeholder="Razão social">` +
                        `<input id="qf-document" class="${inputClass.replace(' mb-2', '')}" placeholder="CNPJ" maxlength="18">` +
                    '</div>',
                focusConfirm: false,
                showCancelButton: true,
                confirmButtonText: 'Cadastrar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#ff8c1e',
                didOpen: () => {
                    const typeEl = document.getElementById('qf-type');
                    const nameEl = document.getElementById('qf-name');
                    const docEl = document.getElementById('qf-document');
                    const applyType = () => {
                        nameEl.placeholder = typeEl.value === 'PF' ? 'Nome completo' : 'Razão social';
                        docEl.placeholder = typeEl.value === 'PF' ? 'CPF' : 'CNPJ';
                        docEl.value = this.formatDocument(docEl.value, typeEl.value);
                    };
                    typeEl.addEventListener('change', applyType);
                    docEl.addEventListener('input', () => { docEl.value = this.formatDocument(docEl.value, typeEl.value); });
                    applyType();
                },
                preConfirm: () => ({
                    name: document.getElementById('qf-name').value,
                    type: document.getElementById('qf-type').value,
                    document: document.getElementById('qf-document').value,
                }),
            });
            if (!form) return;
            try {
                const res = await fetch(this.cfg.urls.fornecedorQuick, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf, Accept: 'application/json' },
                    body: JSON.stringify(form),
                });
                const data = await res.json();
                if (!res.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || 'Erro ao cadastrar.');
                this.cfg.fornecedores.push({ id: data.id, name: data.name, document: data.document });
                this.form.fornecedor_id = data.id;
                this.fornecedorOpen = false;
                window.upAlerts.notifySuccess('Fornecedor cadastrado.');
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },
    };
}
