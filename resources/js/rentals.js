import { maskMoneyDigits } from './money';

/**
 * Painel Kanban da Gestão de Locações (specs/24 §8.2/§8.3) — Pedidos de Locação. As colunas (etapas
 * fixas) chegam prontas do servidor; a partir daí tudo é local (columns) + fetch sob demanda do
 * detalhe do pedido no drawer, no mesmo espírito do `kanban.js` do quadro de processos.
 *
 * `cfg.urls` traz só os caminhos-base (sem id) — as rotas com {id} são montadas aqui, igual ao
 * `cardUrl()` do card-panel.js.
 */
export function rentalsBoard(config) {
    return {
        cfg: config,
        csrf: document.querySelector('meta[name="csrf-token"]').content,

        columns: config.columns,
        sortables: [],

        search: config.initialSearch || '',
        empresaId: config.initialEmpresaId || '',
        showCancelled: config.initialShowCancelled || false,

        createOpen: false,
        returnOpen: false,
        closeOpen: false,

        drawer: {
            open: false,
            loading: false,
            saving: false,
            tab: 'dados',
            order: null,
        },

        newOrder: { empresa_id: '', client_name: '', location: '', start_at: '', end_at: '', notes: '' },
        newLine: { rental_item_id: '', quantity: 1, daily_count: '', unit_price: '' },
        lineAvailability: null,
        newReceipt: { amount: '', received_at: '', finance_payment_source_id: '', notes: '' },
        newCost: { description: '', estimated_value: '', actual_value: '', rental_order_item_id: '' },
        newCostPayment: {},
        closeForm: { revenue_actual: '', costs: {} },
        returnForm: {},

        init() {
            this.$nextTick(() => this.initSortable());
            if (this.cfg.initialOpenOrderId) {
                this.openOrder(Number(this.cfg.initialOpenOrderId));
            }
        },

        // ---- Rede ---------------------------------------------------------
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

        // ---- Montagem de URLs (bases vêm de cfg.urls, sem id) ------------------
        orderShow(id) { return `${this.cfg.urls.pedidoBase}/${id}`; },
        orderUpdate(id) { return `${this.cfg.urls.pedidoBase}/${id}`; },
        orderDestroy(id) { return `${this.cfg.urls.pedidoBase}/${id}`; },
        orderMove(id) { return `${this.cfg.urls.pedidoBase}/${id}/mover`; },
        orderReturn(id) { return `${this.cfg.urls.pedidoBase}/${id}/devolucao`; },
        orderClose(id) { return `${this.cfg.urls.pedidoBase}/${id}/encerrar`; },
        orderReopen(id) { return `${this.cfg.urls.pedidoBase}/${id}/reabrir`; },
        orderCancel(id) { return `${this.cfg.urls.pedidoBase}/${id}/cancelar`; },
        orderReactivate(id) { return `${this.cfg.urls.pedidoBase}/${id}/reativar`; },
        orderLinesStore(id) { return `${this.cfg.urls.pedidoBase}/${id}/itens`; },
        orderLinesRecalculate(id) { return `${this.cfg.urls.pedidoBase}/${id}/itens/recalcular-diarias`; },
        orderCostsStore(id) { return `${this.cfg.urls.pedidoBase}/${id}/custos`; },
        orderReceiptsStore(id) { return `${this.cfg.urls.pedidoBase}/${id}/recebimentos`; },
        lineUpdate(id) { return `${this.cfg.urls.linhaBase}/${id}`; },
        lineDestroy(id) { return `${this.cfg.urls.linhaBase}/${id}`; },
        costUpdate(id) { return `${this.cfg.urls.custoBase}/${id}`; },
        costDestroy(id) { return `${this.cfg.urls.custoBase}/${id}`; },
        costPaymentsStore(id) { return `${this.cfg.urls.custoBase}/${id}/pagamentos`; },
        costPaymentDestroy(id) { return `${this.cfg.urls.pagamentoBase}/${id}`; },
        receiptDestroy(id) { return `${this.cfg.urls.recebimentoBase}/${id}`; },

        reload() {
            const params = new URLSearchParams();
            if (this.search) params.set('search', this.search);
            if (this.empresaId) params.set('empresa_id', this.empresaId);
            if (this.showCancelled) params.set('show_cancelled', '1');
            window.location.href = `${this.cfg.urls.board}?${params.toString()}`;
        },

        // ---- Helpers de exibição ------------------------------------------
        brl(value) {
            const num = Number(value ?? 0);
            return 'R$ ' + num.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        maskInto(obj, key, event) {
            obj[key] = maskMoneyDigits(event.target.value);
        },

        // ---- Novo pedido ----------------------------------------------------
        openCreate() {
            this.newOrder = { empresa_id: '', client_name: '', location: '', start_at: '', end_at: '', notes: '' };
            this.createOpen = true;
        },

        async createOrder() {
            try {
                const { order } = await this.api(this.cfg.urls.ordersStore, 'POST', this.newOrder);
                this.createOpen = false;
                this.upsertCard(order);
                window.upAlerts.notifySuccess('Pedido criado.');
                this.openOrder(order.id);
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        upsertCard(order) {
            this.columns.forEach((col) => {
                col.cards = col.cards.filter((c) => c.id !== order.id);
            });
            const col = this.columns.find((c) => c.status === order.status);
            if (col) col.cards.unshift(order);
        },

        removeCard(orderId) {
            this.columns.forEach((col) => {
                col.cards = col.cards.filter((c) => c.id !== orderId);
            });
        },

        // ---- Drawer: abrir / salvar dados -----------------------------------
        async openOrder(id) {
            this.drawer.open = true;
            this.drawer.loading = true;
            this.drawer.tab = 'dados';
            this.lineAvailability = null;
            try {
                const { order } = await this.api(this.orderShow(id));
                this.drawer.order = order;
            } catch (e) {
                window.upAlerts.notifyError(e.message);
                this.drawer.open = false;
            } finally {
                this.drawer.loading = false;
            }
        },

        closeDrawer() {
            this.drawer.open = false;
            this.drawer.order = null;
        },

        async refreshOrder() {
            const { order } = await this.api(this.orderShow(this.drawer.order.id));
            this.drawer.order = order;
            this.upsertCardFromDetail(order);
        },

        upsertCardFromDetail(order) {
            this.upsertCard({
                id: order.id, code: order.code, status: order.status, position: 0,
                client_label: order.client_label, location: order.location,
                start_at: order.start_at, end_at: order.end_at, period_label: order.period_label,
                revenue_estimated: order.revenue_estimated,
                has_availability_warning: order.has_availability_warning,
                has_return_divergence: order.has_return_divergence,
            });
        },

        async saveOrderData() {
            const o = this.drawer.order;
            this.drawer.saving = true;
            try {
                const { order } = await this.api(this.orderUpdate(o.id), 'PUT', {
                    empresa_id: o.empresa_id || null,
                    client_name: o.client_name || null,
                    location: o.location,
                    start_at: o.start_at || null,
                    end_at: o.end_at || null,
                    discount_type: o.discount_type,
                    discount_value: o.discount_value,
                    revenue_due_date: o.revenue_due_date,
                    notes: o.notes,
                });
                this.drawer.order = order;
                this.upsertCardFromDetail(order);
                window.upAlerts.notifySuccess('Pedido atualizado.');
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            } finally {
                this.drawer.saving = false;
            }
        },

        async confirmRecalculateDailyCounts() {
            const ok = await window.upAlerts.confirmAction({
                title: 'Recalcular diárias?',
                text: 'O período mudou. Recalcular as diárias de todas as linhas pelo novo período?',
                confirmText: 'Recalcular',
            });
            if (!ok) return;
            const { order } = await this.api(this.orderLinesRecalculate(this.drawer.order.id), 'POST');
            this.drawer.order = order;
            this.upsertCardFromDetail(order);
        },

        // ---- Itens do pedido -------------------------------------------------
        onPickLine(itemId) {
            const item = this.cfg.items.find((i) => i.id === Number(itemId));
            this.newLine.unit_price = item ? String(item.rental_price).replace('.', ',') : '';
            this.checkLineAvailability();
        },

        async checkLineAvailability() {
            const o = this.drawer.order;
            if (!this.newLine.rental_item_id || !o.start_at || !o.end_at) {
                this.lineAvailability = null;
                return;
            }
            try {
                const { availability } = await this.api(
                    `${this.cfg.urls.availability}?start=${encodeURIComponent(o.start_at)}&end=${encodeURIComponent(o.end_at)}&order=${o.id}`
                );
                const row = availability.find((a) => a.rental_item_id === Number(this.newLine.rental_item_id));
                this.lineAvailability = row ? row.available : null;
            } catch (e) {
                this.lineAvailability = null;
            }
        },

        async addLine() {
            try {
                const payload = { ...this.newLine, unit_price: this.newLine.unit_price || undefined };
                const { order } = await this.api(this.orderLinesStore(this.drawer.order.id), 'POST', payload);
                this.drawer.order = order;
                this.upsertCardFromDetail(order);
                this.newLine = { rental_item_id: '', quantity: 1, daily_count: '', unit_price: '' };
                this.lineAvailability = null;
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        async updateLine(line) {
            try {
                const { order } = await this.api(this.lineUpdate(line.id), 'PUT', {
                    quantity: line.quantity, daily_count: line.daily_count, unit_price: line.unit_price,
                });
                this.drawer.order = order;
                this.upsertCardFromDetail(order);
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        async removeLine(line) {
            const ok = await window.upAlerts.confirmAction({
                title: 'Remover item?', text: `Remover "${line.item_name}" do pedido?`, confirmText: 'Remover',
            });
            if (!ok) return;
            const { order } = await this.api(this.lineDestroy(line.id), 'DELETE');
            this.drawer.order = order;
            this.upsertCardFromDetail(order);
        },

        // ---- Financeiro: recebimentos -----------------------------------------
        async addReceipt() {
            try {
                const { order } = await this.api(this.orderReceiptsStore(this.drawer.order.id), 'POST', this.newReceipt);
                this.drawer.order = order;
                this.newReceipt = { amount: '', received_at: '', finance_payment_source_id: '', notes: '' };
                window.upAlerts.notifySuccess('Recebimento registrado.');
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        async removeReceipt(receipt) {
            const ok = await window.upAlerts.confirmAction({ title: 'Excluir recebimento?', confirmText: 'Excluir' });
            if (!ok) return;
            const { order } = await this.api(this.receiptDestroy(receipt.id), 'DELETE');
            this.drawer.order = order;
        },

        // ---- Financeiro: custos -------------------------------------------------
        async addCost() {
            try {
                const { order } = await this.api(this.orderCostsStore(this.drawer.order.id), 'POST', this.newCost);
                this.drawer.order = order;
                this.newCost = { description: '', estimated_value: '', actual_value: '', rental_order_item_id: '' };
                window.upAlerts.notifySuccess('Custo lançado.');
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        async updateCost(cost) {
            try {
                const { order } = await this.api(this.costUpdate(cost.id), 'PUT', {
                    description: cost.description, estimated_value: cost.estimated_value,
                    actual_value: cost.actual_value, due_date: cost.due_date,
                });
                this.drawer.order = order;
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        async removeCost(cost) {
            const ok = await window.upAlerts.confirmAction({ title: 'Excluir custo?', confirmText: 'Excluir' });
            if (!ok) return;
            const { order } = await this.api(this.costDestroy(cost.id), 'DELETE');
            this.drawer.order = order;
        },

        async addCostPayment(cost) {
            const form = this.newCostPayment[cost.id] || {};
            try {
                const { order } = await this.api(this.costPaymentsStore(cost.id), 'POST', form);
                this.drawer.order = order;
                this.newCostPayment[cost.id] = {};
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        async removeCostPayment(payment) {
            const ok = await window.upAlerts.confirmAction({ title: 'Excluir pagamento?', confirmText: 'Excluir' });
            if (!ok) return;
            const { order } = await this.api(this.costPaymentDestroy(payment.id), 'DELETE');
            this.drawer.order = order;
        },

        // ---- Máquina de estados --------------------------------------------
        statusLabel(value) {
            return this.cfg.statusLabels[value] || value;
        },

        async confirmOrder() {
            await this.moveStatus(this.drawer.order, 'confirmada');
        },

        async moveStatus(order, status, context = {}) {
            try {
                const { order: updated } = await this.api(this.orderMove(order.id), 'POST', { status, ...context });
                if (this.drawer.order && this.drawer.order.id === order.id) {
                    await this.refreshOrder();
                }
                this.upsertCard(updated);
                return updated;
            } catch (e) {
                window.upAlerts.notifyError(Array.isArray(e.errors?.rental_order) ? e.errors.rental_order.join(' ') : e.message);
                throw e;
            }
        },

        openReturnModal() {
            const o = this.drawer.order;
            this.returnForm = {};
            o.items.forEach((l) => { this.returnForm[l.id] = l.quantity; });
            this.returnOpen = true;
        },

        async submitReturn() {
            try {
                const returns = {};
                Object.keys(this.returnForm).forEach((id) => { returns[id] = Number(this.returnForm[id]); });
                const { order, has_divergence: hasDivergence } = await this.api(
                    this.orderReturn(this.drawer.order.id), 'POST', { returns }
                );
                this.drawer.order = order;
                this.upsertCardFromDetail(order);
                this.returnOpen = false;
                hasDivergence
                    ? window.upAlerts.notifyError('Divergência encontrada na devolução.', 'Atenção')
                    : window.upAlerts.notifySuccess('Devolução conferida sem divergências.');
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        openCloseModal() {
            const o = this.drawer.order;
            this.closeForm = { revenue_actual: String(o.revenue_actual ?? o.revenue_estimated).replace('.', ','), costs: {} };
            o.costs.forEach((c) => {
                this.closeForm.costs[c.id] = String(c.actual_value ?? c.estimated_value).replace('.', ',');
            });
            this.closeOpen = true;
        },

        copyEstimatedToActual() {
            const o = this.drawer.order;
            this.closeForm.revenue_actual = String(o.revenue_estimated).replace('.', ',');
            o.costs.forEach((c) => { this.closeForm.costs[c.id] = String(c.estimated_value).replace('.', ','); });
        },

        async submitClose() {
            try {
                const { order } = await this.api(this.orderClose(this.drawer.order.id), 'POST', this.closeForm);
                this.drawer.order = order;
                this.upsertCardFromDetail(order);
                this.closeOpen = false;
                window.upAlerts.notifySuccess('Pedido encerrado.');
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        async cancelOrder() {
            const { value: reason } = await window.Swal.fire({
                title: 'Cancelar pedido',
                input: 'textarea',
                inputLabel: 'Motivo do cancelamento',
                inputPlaceholder: 'Explique por que o pedido está sendo cancelado...',
                showCancelButton: true,
                confirmButtonText: 'Cancelar pedido',
                cancelButtonText: 'Voltar',
                confirmButtonColor: '#ff8c1e',
                inputValidator: (v) => (!v ? 'Informe o motivo.' : undefined),
            });
            if (!reason) return;
            try {
                const { order } = await this.api(this.orderCancel(this.drawer.order.id), 'POST', { reason });
                this.drawer.order = order;
                this.upsertCardFromDetail(order);
                window.upAlerts.notifySuccess('Pedido cancelado.');
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        async reopenOrder() {
            const ok = await window.upAlerts.confirmAction({
                title: 'Reabrir pedido?', text: 'O pedido volta para Devolvida.', confirmText: 'Reabrir',
            });
            if (!ok) return;
            const { order } = await this.api(this.orderReopen(this.drawer.order.id), 'POST', {});
            this.drawer.order = order;
            this.upsertCardFromDetail(order);
        },

        async reactivateOrder() {
            const ok = await window.upAlerts.confirmAction({
                title: 'Reativar pedido?', text: 'O pedido volta para Orçamento.', confirmText: 'Reativar',
            });
            if (!ok) return;
            const { order } = await this.api(this.orderReactivate(this.drawer.order.id), 'POST', {});
            this.drawer.order = order;
            this.upsertCardFromDetail(order);
        },

        async deleteOrder() {
            const ok = await window.upAlerts.confirmAction({
                title: 'Excluir pedido?', text: 'Esta ação não pode ser desfeita.', confirmText: 'Excluir',
            });
            if (!ok) return;
            try {
                await this.api(this.orderDestroy(this.drawer.order.id), 'DELETE');
                this.removeCard(this.drawer.order.id);
                this.closeDrawer();
                window.upAlerts.notifySuccess('Pedido excluído.');
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        // ---- Drag and drop ---------------------------------------------------
        initSortable() {
            this.destroySortable();
            this.$el.querySelectorAll('[data-column-status] .rental-cards').forEach((list) => {
                this.sortables.push(new window.Sortable(list, {
                    group: 'rentals',
                    animation: 150,
                    ghostClass: 'opacity-40',
                    draggable: '.rental-card',
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
            const fromStatus = evt.from.closest('[data-column-status]').dataset.columnStatus;
            const toStatus = evt.to.closest('[data-column-status]').dataset.columnStatus;
            const newIndex = evt.newIndex;
            const oldIndex = evt.oldIndex;

            evt.item.remove();
            evt.from.insertBefore(evt.item, evt.from.children[oldIndex] || null);

            const fromCol = this.columns.find((c) => c.status === fromStatus);
            const toCol = this.columns.find((c) => c.status === toStatus);
            const idx = fromCol.cards.findIndex((c) => c.id === cardId);
            const [card] = fromCol.cards.splice(idx, 1);
            card.status = toStatus;
            toCol.cards.splice(newIndex, 0, card);

            try {
                await this.api(this.orderMove(cardId), 'POST', { status: toStatus, position: newIndex });
            } catch (e) {
                const revertIdx = toCol.cards.findIndex((c) => c.id === cardId);
                const [reverted] = toCol.cards.splice(revertIdx, 1);
                reverted.status = fromStatus;
                fromCol.cards.splice(idx, 0, reverted);
                window.upAlerts.notifyError(Array.isArray(e.errors?.rental_order) ? e.errors.rental_order.join(' ') : e.message);
            }
        },
    };
}

/** Exclusão de item com motivo obrigatório (specs/24 §2 Q11-Q22) — SweetAlert2 com select + texto. */
export function rentalsItems(config) {
    return {
        cfg: config,
        csrf: document.querySelector('meta[name="csrf-token"]').content,

        async deleteItem(id, name) {
            const { value: formValues } = await window.Swal.fire({
                title: `Excluir "${name}"?`,
                html:
                    '<select id="swal-reason" class="swal2-select" style="display:block;width:100%;margin-top:.5rem;">'
                    + '<option value="venda">Venda</option>'
                    + '<option value="perda">Perda</option>'
                    + '<option value="avaria">Avaria sem conserto</option>'
                    + '<option value="outros">Outros</option>'
                    + '</select>'
                    + '<input id="swal-reason-other" class="swal2-input" placeholder="Descreva o motivo" style="display:none;">',
                didOpen: () => {
                    const select = document.getElementById('swal-reason');
                    const other = document.getElementById('swal-reason-other');
                    select.addEventListener('change', () => {
                        other.style.display = select.value === 'outros' ? 'block' : 'none';
                    });
                },
                focusConfirm: false,
                showCancelButton: true,
                confirmButtonText: 'Excluir',
                confirmButtonColor: '#ff8c1e',
                preConfirm: () => {
                    const reason = document.getElementById('swal-reason').value;
                    const other = document.getElementById('swal-reason-other').value;
                    if (reason === 'outros' && !other) {
                        window.Swal.showValidationMessage('Descreva o motivo.');
                        return false;
                    }
                    return { deletion_reason: reason, deletion_reason_other: other || null };
                },
            });
            if (!formValues) return;

            try {
                const res = await fetch(`${this.cfg.urls.itemBase}/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(formValues),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    throw new Error(data.errors?.rental_item?.[0] || data.message || 'Erro ao excluir.');
                }
                window.upAlerts.notifySuccess('Item excluído.');
                window.location.reload();
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },
    };
}
