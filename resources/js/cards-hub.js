/**
 * Componente Alpine da listagem global "Todos os cards" (todos os quadros, todos os status).
 * Painel de detalhe somente leitura + ações de reabrir/enviar para outro quadro.
 */
export default function cardsHub(config) {
    return {
        cfg: config,
        csrf: document.querySelector('meta[name="csrf-token"]').content,

        panelOpen: false,
        loading: false,
        card: null,
        comments: [],
        attachments: [],
        movements: [],
        newComment: '',
        uploadKind: 'geral',
        actionBoardId: '',

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
                throw err;
            }
            return data;
        },

        cardUrl(suffix = '') {
            return `${this.cfg.cardsBase}/${this.card.id}${suffix}`;
        },

        async openCard(id) {
            this.panelOpen = true;
            this.loading = true;
            this.actionBoardId = '';
            try {
                this.card = await this.api(`${this.cfg.cardsBase}/${id}`);
                this.comments = this.card.comments;
                this.attachments = this.card.attachments;
                this.movements = this.card.movements;
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

        async deleteCard(id, title) {
            if (!(await window.upAlerts.confirmAction({ text: `Excluir o card "${title}"?` }))) return;
            try {
                await this.api(`${this.cfg.cardsBase}/${id}`, 'DELETE');
                window.upAlerts.notifySuccess('Card excluído.');
                window.location.reload();
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        fieldLabel(fieldId) {
            const f = (this.card?.board_fields || []).find((x) => Number(x.id) === Number(fieldId));
            return f ? f.label : null;
        },

        fieldDisplay(fieldId, value) {
            const f = (this.card?.board_fields || []).find((x) => Number(x.id) === Number(fieldId));
            if (!f) return value;
            if (f.type === 'checkbox') return value === '1' || value === true ? 'Sim' : 'Não';
            return value ?? '—';
        },

        async doSendToBoard() {
            if (!this.actionBoardId) return;
            try {
                const r = await this.api(this.cardUrl('/enviar-departamento'), 'POST', { board_id: Number(this.actionBoardId) });
                window.upAlerts.notifySuccess(r.message || 'Card enviado.');
                window.location.reload();
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        async doReopen() {
            if (!this.actionBoardId) return;
            try {
                const r = await this.api(this.cardUrl('/reabrir'), 'POST', { board_id: Number(this.actionBoardId) });
                window.upAlerts.notifySuccess(r.message || 'Card reaberto.');
                window.location.reload();
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        async addComment() {
            if (!this.newComment.trim()) return;
            try {
                const c = await this.api(this.cardUrl('/comentarios'), 'POST', { body: this.newComment });
                this.comments.unshift(c);
                this.newComment = '';
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        async uploadAttachment(event) {
            const file = event.target.files[0];
            if (!file) return;
            const fd = new FormData();
            fd.append('file', file);
            fd.append('kind', this.uploadKind);
            try {
                const res = await fetch(this.cardUrl('/anexos'), {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf, Accept: 'application/json' },
                    body: fd,
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'Falha no upload.');
                this.attachments.push(data);
                event.target.value = '';
                window.upAlerts.notifySuccess('Anexo enviado.');
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },

        async deleteAttachment(att) {
            if (!(await window.upAlerts.confirmAction({ text: `Excluir "${att.original_name}"?` }))) return;
            try {
                await this.api(`${this.cfg.anexoBase}/${att.id}`, 'DELETE');
                this.attachments = this.attachments.filter((a) => a.id !== att.id);
            } catch (e) {
                window.upAlerts.notifyError(e.message);
            }
        },
    };
}
