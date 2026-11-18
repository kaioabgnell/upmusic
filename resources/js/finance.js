/**
 * Financeiro do Evento (specs/23) — componentes Alpine das telas de Custos, Receitas e do
 * Acerto de Sócios.
 *
 * A grade de custos salva LINHA A LINHA (autosave com debounce) e reconcilia com a resposta do
 * servidor: os totais são colunas geradas no banco (`total = unitário x quantidade x diárias`),
 * então o número que vale é sempre o que volta do PUT — o cálculo no cliente existe só para o
 * feedback imediato.
 */

import { maskMoneyDigits, moneyFromDecimal } from './money';

const brl = (value) =>
    (Number(value) || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

/** "1.234,56" (ou "1234.56") -> 1234.56. Mesmo parsing do Br::money() no backend. */
const parseBr = (value) => {
    if (value === null || value === undefined || value === '') return null;
    if (typeof value === 'number') return value;
    const clean = String(value).replace(/[^\d,.-]/g, '').replace(/\./g, '').replace(',', '.');
    return clean === '' || Number.isNaN(Number(clean)) ? null : Number(clean);
};

const num = (value) =>
    (Number(value) || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

async function request(url, method = 'GET', body = null) {
    const res = await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: body ? JSON.stringify(body) : null,
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        throw new Error(data.message || Object.values(data.errors || {}).flat()[0] || 'Não foi possível salvar.');
    }
    return data;
}

/**
 * `unit_actual` entra sempre como decimal cru do servidor (criação, autosave, duplicar) — aqui já
 * nasce como string BR, no mesmo formato que `maskMoneyDigits` escreve ao digitar. Sem essa
 * normalização, a linha mostraria "1234.5" (ponto, sem milhar) logo após salvar, até a próxima
 * edição manual do campo.
 */
function normalizeCostRow(row) {
    return {
        ...row,
        // Os três valores unitários usam a mesma máscara de centavos na grade e na ficha.
        unit_estimated_1: moneyFromDecimal(row.unit_estimated_1),
        unit_estimated_2: moneyFromDecimal(row.unit_estimated_2),
        unit_actual: moneyFromDecimal(row.unit_actual),
    };
}

/** Quantas linhas entram no DOM por quadro na renderização em lotes (ver `renderInBatches`). */
const ROW_BATCH = 25;

export function financeCosts(cfg) {
    return {
        cfg,
        // As linhas chegam depois da página (ver `load`): a casca, o menu e os filtros aparecem
        // na hora e a grade mostra um esqueleto até o JSON chegar.
        rows: (cfg.rows ?? []).map(normalizeCostRow),
        loading: true,
        loadError: null,
        footer: { total: 0, estimated1: 0, estimated2: 0, actual: 0, paid: 0, pending: 0 },
        pagination: { current_page: 1, last_page: 1, total: 0, from: null, to: null },
        loadToken: 0,
        readonly: cfg.readonly ?? false,
        usesSecondEstimate: cfg.usesSecondEstimate ?? false,
        presets: cfg.presets ?? {},
        lookups: cfg.lookups ?? { categorias: [], fornecedores: [] },
        lookupMaps: {},
        // Grupos de coluna com toggle: sem isso, as colunas viram um borrão.
        groups: { estimate1: true, estimate2: cfg.usesSecondEstimate ?? false, actual: true, payments: true },

        // Seletor compartilhado de Item/Empresa: UM painel para a grade inteira, posicionado na
        // célula clicada — em vez de um <select> com dezenas de opções em cada linha.
        picker: { open: false, row: null, field: null, list: null, search: '', active: 0, top: 0, left: 0, width: 288 },

        // Ficha do registro (ícone "ver"): edita o MESMO objeto da linha, então a grade acompanha.
        detail: { open: false, row: null },
        selected: [],
        savingIds: [],
        savedIds: [],
        timers: {},

        // Drawer de documentos / pagamentos da linha.
        drawer: { open: false, tab: 'documentos', loading: false, row: null, documents: [], pending: [], kinds: [], uploadKinds: [], payments: [] },
        newPayment: { finance_payment_source_id: '', bank_account_id: '', amount: '', paid_at: '', notes: '' },
        uploadKind: 'comprovante',

        brl,
        num,
        // Mesma máscara dos valores do card: o valor do pagamento é digitado em centavos.
        maskMoneyDigits,

        init() {
            // Mapas id -> rótulo, para a célula mostrar o nome sem varrer a lista a cada render.
            for (const [key, list] of Object.entries(this.lookups)) {
                this.lookupMaps[key] = new Map(list.map((o) => [String(o.id), o.label]));
            }
            this.load(this.currentPage());
            window.addEventListener('popstate', () => this.load(this.currentPage(), { push: false }));
        },

        currentPage() {
            return Number(new URLSearchParams(window.location.search).get('page')) || 1;
        },

        // ---- Carregamento assíncrono -----------------------------------------
        /**
         * Busca as linhas da página pedida com os mesmos filtros da URL. `loadToken` descarta a
         * resposta de uma requisição antiga que chegue depois da mais nova (troca rápida de página).
         */
        async load(page = 1, { push = true } = {}) {
            const token = ++this.loadToken;
            const params = new URLSearchParams(window.location.search);
            if (page > 1) params.set('page', page); else params.delete('page');

            this.loading = true;
            this.loadError = null;
            this.selected = [];
            try {
                const data = await request(`${this.cfg.urls.rows}?${params.toString()}`);
                if (token !== this.loadToken) return;
                this.footer = data.footer;
                this.pagination = data.pagination;
                if (push) {
                    const qs = params.toString();
                    window.history.replaceState(null, '', `${window.location.pathname}${qs ? `?${qs}` : ''}`);
                }
                this.loading = false;
                await this.renderInBatches(data.rows.map(normalizeCostRow), token);
            } catch (e) {
                if (token !== this.loadToken) return;
                this.loading = false;
                this.loadError = e.message || 'Não foi possível carregar as linhas.';
            }
        },

        /**
         * Entrega as linhas ao DOM em lotes, um por quadro de animação: o primeiro lote aparece
         * imediatamente e o navegador continua respondendo (scroll, menu) enquanto o resto entra.
         */
        async renderInBatches(rows, token) {
            this.rows = rows.slice(0, ROW_BATCH);
            for (let i = ROW_BATCH; i < rows.length; i += ROW_BATCH) {
                await new Promise((resolve) => requestAnimationFrame(resolve));
                if (token !== this.loadToken) return;
                this.rows.push(...rows.slice(i, i + ROW_BATCH));
            }
        },

        goToPage(page) {
            if (page < 1 || page > this.pagination.last_page || page === this.pagination.current_page) return;
            this.load(page);
            this.$refs.grid?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        },

        presetsFor(row) {
            return this.presets[row.fornecedor_categoria_id] ?? [];
        },

        // ---- Seletor compartilhado (Item / Empresa) ---------------------------
        labelFor(list, id) {
            if (id === null || id === undefined || id === '') return null;
            return this.lookupMaps[list]?.get(String(id)) ?? null;
        },

        /**
         * Rótulos das células Item/Empresa. A lista só traz os ativos: um item/fornecedor inativo
         * que a linha já usa cai no rótulo que veio do servidor. Sem fornecedor, as linhas
         * importadas mostram o nome escrito na planilha.
         */
        categoryLabel(row) {
            if (!row.fornecedor_categoria_id) return null;
            return this.labelFor('categorias', row.fornecedor_categoria_id) ?? row.categoria ?? null;
        },

        supplierLabel(row) {
            if (!row.fornecedor_id) return row.supplier_name || null;
            return this.labelFor('fornecedores', row.fornecedor_id) ?? row.supplier_label ?? null;
        },

        get pickerOptions() {
            if (!this.picker.list) return [];
            const term = this.picker.search.trim().toLowerCase();
            const list = this.lookups[this.picker.list] ?? [];
            return term ? list.filter((o) => o.label.toLowerCase().includes(term)) : list;
        },

        openPicker(anchor, row, field, list) {
            if (this.readonly) return;
            const rect = anchor.getBoundingClientRect();
            const width = Math.max(rect.width, 288);
            // Abre para cima quando não cabe embaixo (linhas no fim da tela).
            const below = window.innerHeight - rect.bottom;
            const top = below < 320 ? Math.max(8, rect.top - 324) : rect.bottom + 4;
            const left = Math.min(rect.left, window.innerWidth - width - 8);
            const options = this.lookups[list] ?? [];
            const current = options.findIndex((o) => String(o.id) === String(row[field]));
            this.picker = { open: true, row, field, list, search: '', active: Math.max(0, current + 1), top, left, width };
            this.$nextTick(() => {
                this.$refs.pickerSearch?.focus();
                this.scrollPickerToActive();
            });
        },

        closePicker() {
            this.picker.open = false;
        },

        /** `active` 0 é a opção "sem valor"; as demais são deslocadas em 1. */
        movePicker(step) {
            const max = this.pickerOptions.length;
            this.picker.active = Math.min(max, Math.max(0, this.picker.active + step));
            this.scrollPickerToActive();
        },

        scrollPickerToActive() {
            this.$nextTick(() => {
                this.$refs.pickerList?.querySelector(`[data-index="${this.picker.active}"]`)?.scrollIntoView({ block: 'nearest' });
            });
        },

        choosePicker(option) {
            const { row, field } = this.picker;
            if (!row) return;
            const value = option ? option.id : null;
            this.closePicker();
            if (String(row[field] ?? '') === String(value ?? '')) return;
            row[field] = value;
            this.save(row);
        },

        choosePickerActive() {
            const i = this.picker.active;
            this.choosePicker(i === 0 ? null : this.pickerOptions[i - 1]);
        },

        // ---- Ficha do registro -----------------------------------------------
        openDetail(row) {
            this.closePicker();
            this.detail = { open: true, row };
            this.$nextTick(() => document.getElementById('cost-detail-title')?.focus());
        },

        closeDetail() {
            this.detail.open = false;
        },

        /** Da ficha para o drawer: fecha a ficha antes, para não empilhar dois painéis. */
        detailToDocuments(kind = null) {
            const row = this.detail.row;
            this.closeDetail();
            this.openDocuments(row, kind);
        },

        detailToPayments() {
            const row = this.detail.row;
            this.closeDetail();
            this.openPayments(row);
        },

        /** Quanto do realizado já foi pago (0–100), para a barra da ficha. */
        paidPercent(row) {
            const actual = Number(row?.total_actual) || 0;
            if (actual <= 0) return (Number(row?.paid) || 0) > 0 ? 100 : 0;
            return Math.min(100, Math.round(((Number(row.paid) || 0) / actual) * 100));
        },

        /**
         * Vencimento em destaque (vermelho) quando a data já passou ou é hoje. Compara strings
         * `YYYY-MM-DD` com a data LOCAL — `toISOString()` usaria UTC e viraria o dia às 21h.
         */
        isDueHighlighted(date) {
            if (!date) return false;
            const now = new Date();
            const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
            return date <= today;
        },

        /** URL do link direto do card (specs/18) — abre o quadro já com o modal do card aberto. */
        cardUrl(row) {
            return `${this.cfg.urls.boardCardBase}/${row.card_board_id}/card/${row.card_id}`;
        },

        // ---- Rodapé vivo -------------------------------------------------------
        /**
         * Aplica a resposta do servidor na linha e leva a DIFERENÇA dos totais ao rodapé — assim o
         * rodapé (que veio do servidor, respeitando o filtro) acompanha as edições sem recarregar.
         * `sign` +1 soma a linha inteira (linha nova), -1 tira (linha excluída).
         */
        syncRow(row, data) {
            const before = this.rowTotals(row);
            Object.assign(row, normalizeCostRow(data));
            this.shiftFooter(this.rowTotals(row), before);
        },

        rowTotals(row) {
            return {
                estimated1: Number(row.total_estimated_1) || 0,
                estimated2: Number(row.total_estimated_2) || 0,
                actual: Number(row.total_actual) || 0,
                paid: Number(row.paid) || 0,
            };
        },

        shiftFooter(after, before = { estimated1: 0, estimated2: 0, actual: 0, paid: 0 }, sign = 1) {
            for (const key of ['estimated1', 'estimated2', 'actual', 'paid']) {
                this.footer[key] += sign * (after[key] - before[key]);
            }
            this.footer.pending = this.footer.actual - this.footer.paid;
        },

        // ---- Autosave da linha ------------------------------------------------
        touch(row) {
            clearTimeout(this.timers[row.id]);
            this.timers[row.id] = setTimeout(() => this.save(row), 600);
        },

        async save(row) {
            if (this.readonly) return;
            this.savingIds = [...this.savingIds, row.id];
            try {
                const saved = await request(`${this.cfg.urls.costBase}/${row.id}`, 'PUT', this.payload(row));
                this.syncRow(row, saved);
                this.savedIds = [...this.savedIds, row.id];
                setTimeout(() => { this.savedIds = this.savedIds.filter((id) => id !== row.id); }, 1500);
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            } finally {
                this.savingIds = this.savingIds.filter((id) => id !== row.id);
            }
        },

        payload(row) {
            return {
                fornecedor_categoria_id: row.fornecedor_categoria_id || null,
                cost_center_id: row.cost_center_id || null,
                description: row.description,
                status: row.status,
                art_status: row.art_status,
                fornecedor_id: row.fornecedor_id || null,
                supplier_name: row.supplier_name || null,
                authorized_by: row.authorized_by || null,
                authorized_by_name: row.authorized_by_name || null,
                daily_count: row.daily_count,
                quantity: row.quantity,
                // Previsto 1 não aceita "sem valor" no banco (padrão 0): campo apagado vira zero.
                unit_estimated_1: row.unit_estimated_1 === '' ? 0 : row.unit_estimated_1,
                // String vazia significa "sem valor", não zero — a diferença entre "saiu de graça"
                // e "ainda não aconteceu" existe no banco e precisa sobreviver ao autosave.
                unit_estimated_2: row.unit_estimated_2 === '' ? null : row.unit_estimated_2,
                unit_actual: row.unit_actual === '' ? null : row.unit_actual,
                due_date: row.due_date || null,
                notes: row.notes || null,
            };
        },

        /** Prévia local do total enquanto o servidor não responde. */
        localTotal(row, unitField) {
            const unit = parseBr(row[unitField]);
            if (unit === null) return null;
            return unit * (parseBr(row.quantity) ?? 0) * (parseBr(row.daily_count) ?? 0);
        },

        async addRow() {
            try {
                const created = await request(this.cfg.urls.costStore, 'POST', {
                    description: 'Nova linha',
                    daily_count: 1,
                    quantity: 1,
                    unit_estimated_1: 0,
                });
                this.rows.push(normalizeCostRow(created));
                this.footer.total += 1;
                this.shiftFooter(this.rowTotals(created));
                this.$nextTick(() => {
                    document.getElementById(`linha-${created.id}`)?.querySelector('input[data-field="description"]')?.focus();
                });
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        async duplicate(row) {
            try {
                const copy = await request(`${this.cfg.urls.costBase}/${row.id}/duplicar`, 'POST');
                this.rows.splice(this.rows.indexOf(row) + 1, 0, normalizeCostRow(copy));
                this.footer.total += 1;
                this.shiftFooter(this.rowTotals(copy));
                window.upAlerts.notifySuccess('Linha duplicada. Os documentos não são copiados.');
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        async remove(row) {
            const ok = await window.upAlerts.confirmAction({
                title: 'Excluir linha',
                text: `Excluir "${row.description}"? Os pagamentos e vínculos de documento desta linha saem junto.`,
                confirmText: 'Excluir',
            });
            if (!ok) return;
            try {
                await request(`${this.cfg.urls.costBase}/${row.id}`, 'DELETE');
                this.rows = this.rows.filter((r) => r.id !== row.id);
                this.footer.total -= 1;
                this.shiftFooter(this.rowTotals(row), undefined, -1);
                if (this.detail.row?.id === row.id) this.closeDetail();
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        // ---- Ações em massa ---------------------------------------------------
        get allSelected() {
            return this.rows.length > 0 && this.selected.length === this.rows.length;
        },

        toggleAll() {
            this.selected = this.allSelected ? [] : this.rows.map((r) => r.id);
        },

        async bulk(action, value) {
            if (!this.selected.length) return;
            if (action === 'delete') {
                const ok = await window.upAlerts.confirmAction({
                    title: 'Excluir linhas',
                    text: `Excluir ${this.selected.length} linha(s) selecionada(s)?`,
                    confirmText: 'Excluir',
                });
                if (!ok) return;
            }
            try {
                await request(this.cfg.urls.costBulk, 'POST', { ids: this.selected, action, value });
                // Recarrega só a grade (rodapé incluso), não a página inteira.
                await this.load(this.pagination.current_page);
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        // ---- Drawer de documentos --------------------------------------------
        async openDocuments(row, kind = null) {
            this.drawer = { ...this.drawer, open: true, tab: 'documentos', loading: true, row };
            if (kind) this.uploadKind = kind;
            try {
                const data = await request(`${this.cfg.urls.costBase}/${row.id}/documentos`);
                this.drawer.documents = data.documents;
                this.drawer.pending = data.pending_attachments;
                this.drawer.kinds = data.kinds;
                this.drawer.uploadKinds = data.upload_kinds;
                // O upload direto não oferece "Minuta"; se o chip clicado era esse, cai no padrão.
                if (!this.drawer.uploadKinds.some((k) => k.value === this.uploadKind)) {
                    this.uploadKind = 'comprovante';
                }
                this.syncRow(row, data.item);
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            } finally {
                this.drawer.loading = false;
            }
        },

        async uploadDocument(event) {
            const file = event.target.files[0];
            if (!file) return;
            const fd = new FormData();
            fd.append('file', file);
            fd.append('kind', this.uploadKind);
            try {
                const res = await fetch(`${this.cfg.urls.costBase}/${this.drawer.row.id}/documentos`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        Accept: 'application/json',
                    },
                    body: fd,
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'Falha no upload.');
                this.drawer.documents.push(data.document);
                this.syncRow(this.drawer.row, data.item);
                event.target.value = '';
                window.upAlerts.notifySuccess('Documento anexado.');
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        async attachPending(attachment, kind) {
            try {
                const data = await request(`${this.cfg.urls.costBase}/${this.drawer.row.id}/documentos/vincular`, 'POST', {
                    card_attachment_id: attachment.id,
                    kind,
                });
                this.drawer.documents.push(data.document);
                this.drawer.pending = this.drawer.pending.filter((p) => p.id !== attachment.id);
                this.syncRow(this.drawer.row, data.item);
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        async deleteDocument(doc) {
            const extra = doc.from_card
                ? ' O arquivo continua no card — sai apenas do controle documental desta linha.'
                : '';
            const ok = await window.upAlerts.confirmAction({ text: `Remover "${doc.name}"?${extra}`, confirmText: 'Remover' });
            if (!ok) return;
            try {
                const data = await request(`${this.cfg.urls.documentBase}/${doc.id}`, 'DELETE');
                this.drawer.documents = this.drawer.documents.filter((d) => d.id !== doc.id);
                this.syncRow(this.drawer.row, data.item);
                if (doc.from_card) this.openDocuments(this.drawer.row);
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        // ---- Pagamentos -------------------------------------------------------
        async openPayments(row) {
            this.drawer = { ...this.drawer, open: true, tab: 'pagamentos', loading: true, row };
            try {
                const data = await request(`${this.cfg.urls.costBase}/${row.id}/pagamentos`);
                // _savedAccount guarda a conta gravada, para desfazer a troca se o PUT falhar.
                this.drawer.payments = data.payments.map((p) => ({ ...p, _savedAccount: p.bank_account_id }));
                this.syncRow(row, data.item);
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            } finally {
                this.drawer.loading = false;
            }
        },

        async addPayment() {
            const amount = parseBr(this.newPayment.amount);
            if (!amount || !this.newPayment.finance_payment_source_id) {
                window.upAlerts.notifyError('Informe o grupo de pagamento e o valor.');
                return;
            }

            // Pagar acima do realizado é permitido (acontece), mas nunca em silêncio.
            const pending = Number(this.drawer.row.pending ?? 0);
            if (amount > pending + 0.005) {
                const ok = await window.upAlerts.confirmAction({
                    title: 'Pagamento acima do realizado',
                    text: `Falta pagar ${brl(pending)} nesta linha e o valor informado é ${brl(amount)}. Registrar assim mesmo?`,
                    confirmText: 'Registrar',
                });
                if (!ok) return;
            }

            try {
                const data = await request(`${this.cfg.urls.costBase}/${this.drawer.row.id}/pagamentos`, 'POST', {
                    ...this.newPayment,
                    bank_account_id: this.newPayment.bank_account_id || null,
                    amount,
                });
                this.drawer.payments.unshift({ ...data.payment, _savedAccount: data.payment.bank_account_id });
                this.syncRow(this.drawer.row, data.item);
                this.newPayment = { finance_payment_source_id: '', bank_account_id: '', amount: '', paid_at: '', notes: '' };
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        // Troca a conta bancária de um pagamento já lançado (specs/26). O PUT valida o pagamento
        // inteiro, então reenvia os demais campos como estão; se falhar, volta a conta anterior.
        async updatePaymentAccount(payment) {
            const previous = payment._savedAccount ?? null;
            try {
                const data = await request(`${this.cfg.urls.paymentBase}/${payment.id}`, 'PUT', {
                    finance_payment_source_id: payment.finance_payment_source_id,
                    bank_account_id: payment.bank_account_id || null,
                    amount: payment.amount,
                    paid_at: payment.paid_at,
                    notes: payment.notes,
                });
                Object.assign(payment, data.payment, { _savedAccount: data.payment.bank_account_id });
                window.upAlerts.notifySuccess('Conta bancária atualizada.');
            } catch (e) {
                payment.bank_account_id = previous;
                window.upAlerts.notifyError(e.message);
            }
        },

        async deletePayment(payment) {
            const ok = await window.upAlerts.confirmAction({ text: `Excluir o pagamento de ${brl(payment.amount)}?`, confirmText: 'Excluir' });
            if (!ok) return;
            try {
                const data = await request(`${this.cfg.urls.paymentBase}/${payment.id}`, 'DELETE');
                this.drawer.payments = this.drawer.payments.filter((p) => p.id !== payment.id);
                this.syncRow(this.drawer.row, data.item);
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        // ---- Rodapé (respeita o filtro, como o servidor devolveu) -------------
        get totals() {
            const sum = (fn) => this.rows.reduce((acc, r) => acc + (Number(fn(r)) || 0), 0);
            return {
                estimated1: sum((r) => r.total_estimated_1),
                estimated2: sum((r) => r.total_estimated_2),
                actual: sum((r) => r.total_actual),
                paid: sum((r) => r.paid),
                pending: sum((r) => r.pending),
            };
        },
    };
}

/**
 * Previsto/realizado/recebido entram como decimal cru do servidor — nascem aqui já em string BR,
 * no mesmo formato que `maskMoneyDigits` escreve ao digitar (ver `normalizeCostRow`, mesma razão).
 */
function normalizeRevenueRow(row) {
    return {
        ...row,
        estimated_value: moneyFromDecimal(row.estimated_value),
        actual_value: moneyFromDecimal(row.actual_value),
        received_value: moneyFromDecimal(row.received_value),
    };
}

export function financeRevenues(cfg) {
    return {
        cfg,
        rows: (cfg.rows ?? []).map(normalizeRevenueRow),
        readonly: cfg.readonly ?? false,
        timers: {},
        savingIds: [],
        brl,
        num,
        maskMoneyDigits,

        touch(row) {
            clearTimeout(this.timers[row.id]);
            this.timers[row.id] = setTimeout(() => this.save(row), 600);
        },

        async save(row) {
            if (this.readonly) return;
            this.savingIds = [...this.savingIds, row.id];
            try {
                const saved = await request(`${this.cfg.urls.revenueBase}/${row.id}`, 'PUT', {
                    category: row.category,
                    description: row.description || null,
                    cost_center_id: row.cost_center_id || null,
                    empresa_id: row.empresa_id || null,
                    estimated_value: row.estimated_value,
                    actual_value: row.actual_value,
                    received_value: row.received_value,
                    finance_payment_source_id: row.finance_payment_source_id || null,
                    bank_account_id: row.bank_account_id || null,
                    received_at: row.received_at || null,
                    notes: row.notes || null,
                });
                Object.assign(row, normalizeRevenueRow(saved));
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            } finally {
                this.savingIds = this.savingIds.filter((id) => id !== row.id);
            }
        },

        async addRow(category = 'patrocinio') {
            try {
                const created = await request(this.cfg.urls.revenueStore, 'POST', {
                    category,
                    estimated_value: 0,
                    actual_value: 0,
                    received_value: 0,
                });
                this.rows.push(normalizeRevenueRow(created));
                this.$nextTick(() => {
                    document.getElementById(`receita-${created.id}`)?.querySelector('input[data-field="description"]')?.focus();
                });
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        async remove(row) {
            const ok = await window.upAlerts.confirmAction({ text: 'Excluir esta linha de receita?', confirmText: 'Excluir' });
            if (!ok) return;
            try {
                await request(`${this.cfg.urls.revenueBase}/${row.id}`, 'DELETE');
                this.rows = this.rows.filter((r) => r.id !== row.id);
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        get totals() {
            const sum = (fn) => this.rows.reduce((acc, r) => acc + (parseBr(fn(r)) ?? 0), 0);
            return {
                estimated: sum((r) => r.estimated_value),
                actual: sum((r) => r.actual_value),
                received: sum((r) => r.received_value),
                pending: sum((r) => r.actual_value) - sum((r) => r.received_value),
            };
        },
    };
}

export function financeSettlements(cfg) {
    return {
        partners: cfg.partners ?? [],
        result: cfg.result ?? 0,
        brl,

        /** Espelha a regra do serviço: total = resultado realizado x porcentagem. */
        amountOf(partner) {
            return partner.manual_amount ? partner.amount : (this.result * (Number(partner.percentage) || 0)) / 100;
        },

        get totalPct() {
            return this.partners.reduce((acc, p) => acc + (Number(p.percentage) || 0), 0);
        },
    };
}
