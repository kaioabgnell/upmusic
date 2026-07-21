/**
 * Lógica compartilhada do modal de card (`boards/partials/card-panel.blade.php`), usado tanto
 * pelo Kanban de um quadro (`kanban.js`) quanto pela listagem global "Todos os cards"
 * (`cards-hub.js`) — mesmo componente, para que qualquer alteração no modal valha nos dois locais.
 *
 * Cada host chama `cardPanel({ ...seu próprio estado/métodos... })` e define, se precisar, "hooks"
 * opcionais chamados nos pontos de mutação (`afterCardOpened`, `afterCardSaved`, `afterCardRemoved`,
 * `afterCardMoved`, `afterCardTransferred`, `afterCardConcluded`, `afterCardReopened`,
 * `afterCardDuplicated`, `afterCardArchived`, `afterCardUnarchived`, `bumpCardCount`) — o Kanban usa
 * esses hooks para atualizar seu array reativo de colunas/cards sem reload; a listagem global
 * simplesmente recarrega a página.
 *
 * IMPORTANTE: a mesclagem usa `Object.getOwnPropertyDescriptors` + `Object.defineProperties`, não
 * `{ ...a, ...b }` — o objeto abaixo tem várias propriedades `get` (computeds). Um spread comum
 * *avalia* cada getter na hora da cópia (perdendo a reatividade) e o faria com `this` apontando para
 * este objeto solto (sem `columns`/`cfg`, que só existem no host), quebrando com
 * "Cannot read properties of undefined" antes mesmo do componente terminar de montar.
 */
function cardPanelBase() {
    return {
        panelOpen: false,
        mode: 'view', // 'view' | 'create'
        loading: false,
        saving: false,
        cardId: null,
        columnId: null,
        concludedAt: null,
        concludedBy: null,
        archivedAt: null,
        archivedBy: null,
        tab: 'detalhes', // 'detalhes' | 'comentarios' | 'historico'

        actionsMenuOpen: false,

        form: {},
        errors: {},
        comments: [],
        attachments: [],
        movements: [],
        newComment: '',
        uploadKind: 'geral',

        transferBoardId: '',

        assigneeOpen: false,
        assigneeSearch: '',
        dueOpen: false,
        priorityOpen: false,
        fornecedorOpen: false,
        fornecedorSearch: '',

        // Histórico de preços do fornecedor selecionado (tooltip ao lado de "Valor previsto").
        // Cacheado por fornecedor_id — não depende do card aberto, só do fornecedor.
        fornecedorHistoryCache: {},
        fornecedorHistoryLoading: false,
        fornecedorHistoryPos: { top: 0, left: 0 },

        // Aviso sob "Valor previsto" comparando com o Preço Interno da categoria do fornecedor
        // (recalculado só ao sair do campo — ver checkEstimatedValueVsPrecoInterno).
        estimatedValueCheck: null,

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

        // ---- Painel: criar / abrir --------------------------------------------
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
            this.concludedAt = null;
            this.concludedBy = null;
            this.archivedAt = null;
            this.archivedBy = null;
            this.form = this.blankForm(columnId);
            this.errors = {};
            this.comments = []; this.attachments = []; this.movements = [];
            this.actionsMenuOpen = false;
            this.assigneeOpen = false; this.assigneeSearch = ''; this.dueOpen = false; this.priorityOpen = false;
            this.fornecedorOpen = false; this.fornecedorSearch = '';
            this.estimatedValueCheck = null;
            this.tab = 'detalhes';
            this.panelOpen = true;
        },

        async openCard(id) {
            this.mode = 'view';
            this.panelOpen = true;
            this.loading = true;
            this.errors = {};
            this.actionsMenuOpen = false;
            this.assigneeOpen = false; this.assigneeSearch = ''; this.dueOpen = false; this.priorityOpen = false;
            this.fornecedorOpen = false; this.fornecedorSearch = '';
            this.estimatedValueCheck = null;
            this.tab = 'detalhes';
            try {
                const c = await this.api(this.cardUrl(id));
                // Ponto de extensão: a listagem global usa isso para carregar o config do quadro
                // deste card (campos/colunas/quadros de destino), já que a página não é de um único quadro.
                await this.afterCardOpened?.(c);
                this.cardId = c.id;
                this.columnId = c.board_column_id;
                this.concludedAt = c.concluded_at;
                this.concludedBy = c.concluded_by;
                this.archivedAt = c.archived_at;
                this.archivedBy = c.archived_by;
                this.cfg.fields = c.board_fields;
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
                    board_column_id: c.board_column_id, board_id: c.board_id, fields,
                };
                this.comments = c.comments;
                this.attachments = c.attachments;
                this.movements = c.movements;
                this.loadFornecedorHistory(this.form.fornecedor_id);
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

        // "Vencido": o prazo (due_date) é anterior a hoje. Comparação lexical de datas em Y-m-d
        // (mesmo formato que vem do backend) — evita fuso/parsing. Um card concluído não é marcado
        // como vencido: o header já mostra "Concluído" e o card não está mais em aberto.
        get isOverdue() {
            if (!this.form.due_date || this.concludedAt) return false;
            const now = new Date();
            const todayStr = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
            return this.form.due_date < todayStr;
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
                this.afterCardMoved?.(Number(this.columnId), Number(columnId), this.cardId);
                // window.upAlerts.notifySuccess('Card movido.');
                this.closePanel();
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        columnPillStyle(color) {
            const c = color || '#8a8a8a';
            return `background: ${c}1f; color: ${c};`;
        },

        // ---- Ações rápidas: responsável / vencimento / prioridade -------------
        get selectedAssignee() {
            return this.cfg.assignees.find((u) => u.id === Number(this.form.assignee_id)) || null;
        },

        get filteredAssignees() {
            const q = this.assigneeSearch.trim().toLowerCase();
            if (!q) return this.cfg.assignees;
            return this.cfg.assignees.filter((u) => u.name.toLowerCase().includes(q));
        },

        // ---- Fornecedor (select pesquisável + cadastro rápido) ----------------
        get selectedFornecedor() {
            return this.cfg.fornecedores.find((f) => f.id === Number(this.form.fornecedor_id)) || null;
        },

        get filteredFornecedores() {
            const q = this.fornecedorSearch.trim().toLowerCase();
            if (!q) return this.cfg.fornecedores;
            return this.cfg.fornecedores.filter((f) => f.name.toLowerCase().includes(q) || f.document.includes(q));
        },

        get fornecedorHistory() {
            return this.fornecedorHistoryCache[this.form.fornecedor_id] || null;
        },

        async loadFornecedorHistory(fornecedorId) {
            if (!fornecedorId || this.fornecedorHistoryCache[fornecedorId]) return;
            this.fornecedorHistoryLoading = true;
            try {
                this.fornecedorHistoryCache[fornecedorId] = await this.api(`${this.cfg.urls.fornecedorPriceHistory}/${fornecedorId}/preco-historico`);
            } catch (e) {
                this.fornecedorHistoryCache[fornecedorId] = { records: [], average: null, trend: null };
            } finally {
                this.fornecedorHistoryLoading = false;
            }
        },

        // Popover de histórico de preços do fornecedor: posição fixa (viewport), calculada a partir
        // do ícone, para escapar do scroll do corpo do painel.
        positionFornecedorHistoryTooltip(event) {
            const rect = event.currentTarget.getBoundingClientRect();
            this.fornecedorHistoryPos = { top: rect.bottom + 8, left: rect.left };
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

        // ---- Salvar ------------------------------------------------------------
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
                this.afterCardSaved?.(card);
                // O salvamento pode ter criado/atualizado um registro de preço para este fornecedor
                // (hook de sincronização) — invalida o cache para não mostrar histórico desatualizado.
                delete this.fornecedorHistoryCache[this.form.fornecedor_id];
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
                this.afterCardRemoved?.(this.cardId);
                window.upAlerts.notifySuccess('Card excluído.');
                this.closePanel();
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        async duplicate() {
            const confirmed = await window.upAlerts.confirmAction({
                title: 'Duplicar card?',
                text: 'Uma cópia será criada na mesma coluna, com "[CÓPIA]" no título.',
                confirmText: 'Duplicar',
                icon: 'question',
            });
            if (!confirmed) return;
            try {
                const card = await this.api(this.cardUrl(this.cardId, '/duplicar'), 'POST');
                this.afterCardDuplicated?.(card);
                window.upAlerts.notifySuccess('Card duplicado.');
                this.closePanel();
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        async doArchive() {
            const confirmed = await window.upAlerts.confirmAction({
                title: 'Arquivar card?',
                text: 'O card deixará de aparecer no quadro. Você poderá desarquivá-lo depois em "Todos os cards".',
                confirmText: 'Arquivar',
                icon: 'question',
            });
            if (!confirmed) return;
            try {
                const r = await this.api(this.cardUrl(this.cardId, '/arquivar'), 'POST');
                this.afterCardArchived?.();
                window.upAlerts.notifySuccess(r.message || 'Card arquivado.');
                this.closePanel();
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        async doUnarchive() {
            try {
                const r = await this.api(this.cardUrl(this.cardId, '/desarquivar'), 'POST');
                this.archivedAt = null;
                this.archivedBy = null;
                this.afterCardUnarchived?.();
                window.upAlerts.notifySuccess(r.message || 'Card desarquivado.');
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        // ---- Comentários -------------------------------------------------------
        async addComment() {
            if (!this.newComment.trim()) return;
            try {
                const c = await this.api(this.cardUrl(this.cardId, '/comentarios'), 'POST', { body: this.newComment });
                this.comments.unshift(c);
                this.newComment = '';
                this.bumpCardCount?.(this.cardId, 'comments_count', 1);
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        // ---- Anexos -------------------------------------------------------------
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
                this.bumpCardCount?.(this.cardId, 'attachments_count', 1);
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
                this.bumpCardCount?.(this.cardId, 'attachments_count', -1);
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        // ---- Transferência / conclusão / reabertura ----------------------------
        async doTransfer() {
            if (!this.transferBoardId) return;
            try {
                const r = await this.api(this.cardUrl(this.cardId, '/enviar-departamento'), 'POST', {
                    board_id: Number(this.transferBoardId),
                });
                this.afterCardTransferred?.();
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
                this.afterCardConcluded?.();
                window.upAlerts.notifySuccess(r.message || 'Card concluído.');
                this.closePanel();
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        async doReopen() {
            if (!this.transferBoardId) return;
            try {
                const r = await this.api(this.cardUrl(this.cardId, '/reabrir'), 'POST', {
                    board_id: Number(this.transferBoardId),
                });
                this.afterCardReopened?.();
                window.upAlerts.notifySuccess(r.message || 'Card reaberto.');
                this.closePanel();
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        // ---- Máscara de dinheiro (R$) -------------------------------------------
        // Formata o valor vindo do banco (ex.: "1500.00") para o padrão BR ("1.500,00")
        // ao abrir um card existente. A digitação em si é mascarada pelo plugin
        // @alpinejs/mask (x-mask:dynamic="$money($input, ',')" no card-panel).
        moneyFromDecimal(value) {
            if (value === null || value === undefined || value === '') return '';
            const num = Number(value);
            return Number.isNaN(num) ? '' : num.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        // Caminho inverso de moneyFromDecimal: o input mascarado guarda "1.500,00" (BR); aqui
        // convertemos de volta para número pra poder comparar com o Preço Interno da categoria.
        parseMoneyBR(value) {
            if (value === null || value === undefined || value === '') return null;
            const num = Number(String(value).replace(/\./g, '').replace(',', '.'));
            return Number.isNaN(num) ? null : num;
        },

        // Ao sair do campo "Valor previsto", compara com o Preço Interno da categoria do fornecedor
        // selecionado e mostra um aviso logo abaixo do input (ver card-panel.blade.php).
        checkEstimatedValueVsPrecoInterno() {
            const precoInterno = this.selectedFornecedor?.preco_interno;
            const valor = this.parseMoneyBR(this.form.estimated_value);
            if (precoInterno === null || precoInterno === undefined || valor === null) {
                this.estimatedValueCheck = null;
                return;
            }
            const precoFmt = precoInterno.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            this.estimatedValueCheck = valor > precoInterno
                ? { above: true, message: `Valor acima do Preço Interno da categoria (R$ ${precoFmt}).` }
                : { above: false, message: `Valor dentro do Preço Interno da categoria (R$ ${precoFmt}).` };
        },

        // ---- Cadastro rápido de empresa / fornecedor -----------------------------
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
                this.cfg.fornecedores.push({ id: data.id, name: data.name, document: data.document, preco_interno: null });
                this.form.fornecedor_id = data.id;
                this.fornecedorOpen = false;
                this.loadFornecedorHistory(data.id);
                window.upAlerts.notifySuccess('Fornecedor cadastrado.');
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },
    };
}

/**
 * Mescla o estado/métodos compartilhados com o próprio do host (`own`), preservando os getters do
 * card-panel como acessores de verdade (ver aviso acima) — use isto no lugar de
 * `{ ...cardPanel(), ...own }` em `kanban.js`/`cards-hub.js`.
 */
export default function cardPanel(own = {}) {
    const merged = {};
    Object.defineProperties(merged, Object.getOwnPropertyDescriptors(cardPanelBase()));
    Object.defineProperties(merged, Object.getOwnPropertyDescriptors(own));
    return merged;
}
