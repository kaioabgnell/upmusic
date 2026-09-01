/**
 * Financeiro do Evento (specs/23) — componentes Alpine das telas de Custos, Receitas e do
 * Acerto de Sócios.
 *
 * A grade de custos salva LINHA A LINHA (autosave com debounce) e reconcilia com a resposta do
 * servidor: os totais são colunas geradas no banco (`total = unitário x quantidade x diárias`),
 * então o número que vale é sempre o que volta do PUT — o cálculo no cliente existe só para o
 * feedback imediato.
 */

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

export function financeCosts(cfg) {
    return {
        cfg,
        rows: cfg.rows ?? [],
        readonly: cfg.readonly ?? false,
        usesSecondEstimate: cfg.usesSecondEstimate ?? false,
        presets: cfg.presets ?? {},
        // Grupos de coluna com toggle: sem isso, 26 colunas viram um borrão.
        groups: { estimate1: true, estimate2: cfg.usesSecondEstimate ?? false, actual: true, payments: true, control: true },
        selected: [],
        savingIds: [],
        savedIds: [],
        timers: {},

        // Drawer de documentos / pagamentos da linha.
        drawer: { open: false, tab: 'documentos', loading: false, row: null, documents: [], pending: [], kinds: [], payments: [] },
        newPayment: { finance_payment_source_id: '', amount: '', paid_at: '', notes: '' },
        uploadKind: 'comprovante',

        brl,
        num,

        presetsFor(row) {
            return this.presets[row.fornecedor_categoria_id] ?? [];
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
                Object.assign(row, saved);
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
                description: row.description,
                status: row.status,
                art_status: row.art_status,
                fornecedor_id: row.fornecedor_id || null,
                supplier_name: row.supplier_name || null,
                authorized_by: row.authorized_by || null,
                authorized_by_name: row.authorized_by_name || null,
                daily_count: row.daily_count,
                quantity: row.quantity,
                unit_estimated_1: row.unit_estimated_1,
                // String vazia significa "sem valor", não zero — a diferença entre "saiu de graça"
                // e "ainda não aconteceu" existe no banco e precisa sobreviver ao autosave.
                unit_estimated_2: row.unit_estimated_2 === '' ? null : row.unit_estimated_2,
                unit_actual: row.unit_actual === '' ? null : row.unit_actual,
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
                this.rows.push(created);
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
                this.rows.splice(this.rows.indexOf(row) + 1, 0, copy);
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
                window.location.reload();
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
                Object.assign(row, data.item);
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
                Object.assign(this.drawer.row, data.item);
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
                Object.assign(this.drawer.row, data.item);
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
                Object.assign(this.drawer.row, data.item);
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
                this.drawer.payments = data.payments;
                Object.assign(row, data.item);
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
                    amount,
                });
                this.drawer.payments.unshift(data.payment);
                Object.assign(this.drawer.row, data.item);
                this.newPayment = { finance_payment_source_id: '', amount: '', paid_at: '', notes: '' };
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        async deletePayment(payment) {
            const ok = await window.upAlerts.confirmAction({ text: `Excluir o pagamento de ${brl(payment.amount)}?`, confirmText: 'Excluir' });
            if (!ok) return;
            try {
                const data = await request(`${this.cfg.urls.paymentBase}/${payment.id}`, 'DELETE');
                this.drawer.payments = this.drawer.payments.filter((p) => p.id !== payment.id);
                Object.assign(this.drawer.row, data.item);
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

export function financeRevenues(cfg) {
    return {
        cfg,
        rows: cfg.rows ?? [],
        readonly: cfg.readonly ?? false,
        timers: {},
        savingIds: [],
        brl,
        num,

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
                    empresa_id: row.empresa_id || null,
                    estimated_value: row.estimated_value,
                    actual_value: row.actual_value,
                    received_value: row.received_value,
                    finance_payment_source_id: row.finance_payment_source_id || null,
                    received_at: row.received_at || null,
                    notes: row.notes || null,
                });
                Object.assign(row, saved);
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
                this.rows.push(created);
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
