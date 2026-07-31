/**
 * Painel de notificações do sino da topbar (specs/22 §5.2).
 *
 * Lista paginada por cursor (10 em 10, scroll infinito), filtro "somente não lidas" e contador de
 * não lidas atualizado por polling — não há WebSocket no projeto (BROADCAST_DRIVER=log).
 */
export default function notifications({ initialUnread = 0, urls = {} } = {}) {
    return {
        open: false,
        items: [],
        unreadCount: initialUnread,
        filter: 'todas', // 'todas' | 'nao_lidas'
        cursor: null,
        hasMore: true,
        loading: false,
        loaded: false,

        init() {
            this.startPolling();
        },

        toggle() {
            this.open = !this.open;
            // Recarrega a cada abertura. Reaproveitar o que já estava em memória deixava a lista
            // velha: notificação que chegasse depois da primeira abertura só aparecia com F5.
            if (this.open) this.load({ reset: true });
        },

        setFilter(value) {
            if (this.filter === value) return;
            this.filter = value;
            this.load({ reset: true });
        },

        async load({ reset = false } = {}) {
            if (this.loading || (!reset && !this.hasMore)) return;
            this.loading = true;

            const cursor = reset ? null : this.cursor;
            if (reset && this.$refs.list) this.$refs.list.scrollTop = 0;

            const params = new URLSearchParams({ filtro: this.filter });
            if (cursor) params.set('antes', cursor);

            try {
                const res = await fetch(`${urls.index}?${params}`, {
                    headers: { Accept: 'application/json' },
                });
                if (!res.ok) throw new Error(res.status);
                const data = await res.json();

                // No reset a lista só é substituída quando a resposta chega — limpar antes faria o
                // skeleton piscar a cada reabertura do painel.
                this.items = reset ? data.items : [...this.items, ...data.items];
                this.cursor = data.next_cursor;
                this.hasMore = data.next_cursor !== null;
                this.unreadCount = data.unread_count;
                this.loaded = true;
            } catch (e) {
                window.upAlerts?.notifyError('Não foi possível carregar suas notificações.');
            } finally {
                this.loading = false;
            }
        },

        /** Scroll infinito: pede a próxima página ~80px antes do fim, sem o usuário bater no fundo. */
        onScroll(event) {
            const el = event.target;
            if (el.scrollHeight - el.scrollTop - el.clientHeight < 80) this.load();
        },

        async openItem(item) {
            // Marca como lida antes de sair da página; `silent` evita remover o item da lista
            // (a navegação já está a caminho e a remoção causaria um "pulo" visual).
            if (!item.is_read) await this.markRead(item, { silent: true });
            window.location.href = item.url;
        },

        async markRead(item, { silent = false } = {}) {
            if (item.is_read) return;

            item.is_read = true; // otimista
            try {
                const res = await fetch(urls.read.replace('__ID__', item.id), {
                    method: 'POST',
                    headers: this.headers(),
                });
                if (!res.ok) throw new Error(res.status);
                this.unreadCount = (await res.json()).unread_count;

                // No filtro "não lidas" o item deixa de pertencer à lista depois de lido.
                if (!silent && this.filter === 'nao_lidas') {
                    this.items = this.items.filter((i) => i.id !== item.id);
                }
            } catch (e) {
                item.is_read = false; // desfaz o otimismo
                window.upAlerts?.notifyError('Não foi possível marcar a notificação como lida.');
            }
        },

        async markAllRead() {
            try {
                const res = await fetch(urls.readAll, { method: 'POST', headers: this.headers() });
                if (!res.ok) throw new Error(res.status);
                this.unreadCount = (await res.json()).unread_count;

                this.items.forEach((i) => {
                    i.is_read = true;
                });
                if (this.filter === 'nao_lidas') this.items = [];
            } catch (e) {
                window.upAlerts?.notifyError('Não foi possível marcar as notificações como lidas.');
            }
        },

        /**
         * Polling do contador: 60s, parado com a aba em segundo plano e disparado ao voltar o
         * foco — quem retorna para a aba vê o número certo na hora, sem esperar o próximo ciclo.
         */
        startPolling() {
            setInterval(() => {
                if (!document.hidden) this.refreshCount();
            }, 60000);

            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) this.refreshCount();
            });
        },

        async refreshCount() {
            try {
                const res = await fetch(urls.count, { headers: { Accept: 'application/json' } });
                if (!res.ok) return;
                this.unreadCount = (await res.json()).unread_count;
            } catch (e) {
                // Falha de rede no polling é silenciosa: o próximo ciclo tenta de novo.
            }
        },

        headers() {
            return {
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
            };
        },
    };
}
