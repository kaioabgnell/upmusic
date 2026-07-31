{{--
    Sino de notificações da topbar (specs/22 §5.1 e §5.3).

    `$unreadCount` vem do NotificationComposer (registrado no AppServiceProvider), para o badge já
    sair correto no HTML em vez de piscar em 0 até o primeiro fetch.
--}}
<div x-data="notifications({
        initialUnread: {{ (int) ($unreadCount ?? 0) }},
        urls: {
            index: '{{ route('notifications.index') }}',
            count: '{{ route('notifications.count') }}',
            read: '{{ route('notifications.read', ['notification' => '__ID__']) }}',
            readAll: '{{ route('notifications.read-all') }}',
        },
     })"
     class="relative"
     @click.outside="open = false"
     @keydown.escape.window="open = false">

    <button type="button" @click="toggle()"
            class="relative w-9 h-9 flex items-center justify-center rounded-full text-steel hover:text-brand-ink hover:bg-surface transition-colors focus:outline-none"
            aria-label="Notificações">
        <i class="fa-solid fa-bell text-lg"></i>
        <span x-show="unreadCount > 0" x-cloak
              x-text="unreadCount > 99 ? '99+' : unreadCount"
              class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-brand-orange text-brand-ink text-[10px] font-bold flex items-center justify-center"></span>
    </button>

    {{-- Painel --}}
    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="absolute right-0 mt-2 z-50 w-[calc(100vw-2rem)] sm:w-96 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 overflow-hidden">

        {{-- Cabeçalho fixo --}}
        <div class="px-4 py-3 border-b border-hairline">
            <div class="flex items-center justify-between gap-2">
                <h3 class="text-sm font-semibold text-brand-ink">Notificações</h3>
                <button type="button" x-show="unreadCount > 0" @click="markAllRead()"
                        class="text-xs font-medium text-brand-orange-deep hover:underline">
                    Marcar todas como lidas
                </button>
            </div>

            {{-- Abas --}}
            <div class="mt-3 flex items-center gap-2">
                <button type="button" @click="setFilter('todas')"
                        :class="filter === 'todas' ? 'bg-brand-ink text-white' : 'bg-surface text-steel hover:text-brand-ink'"
                        class="px-3 py-1 rounded-full text-xs font-medium transition-colors">
                    Todas
                </button>
                <button type="button" @click="setFilter('nao_lidas')"
                        :class="filter === 'nao_lidas' ? 'bg-brand-ink text-white' : 'bg-surface text-steel hover:text-brand-ink'"
                        class="px-3 py-1 rounded-full text-xs font-medium transition-colors">
                    Não lidas
                    <span x-show="unreadCount > 0" x-text="unreadCount" class="ml-1 font-bold"></span>
                </button>
            </div>
        </div>

        {{-- Lista rolável (scroll infinito) --}}
        <div x-ref="list" @scroll.passive="onScroll($event)" class="max-h-[70vh] overflow-y-auto divide-y divide-hairline">

            {{-- Skeletons da primeira carga --}}
            <template x-if="loading && items.length === 0">
                <div>
                    <template x-for="i in 3" :key="i">
                        <div class="flex items-start gap-3 px-4 py-3 animate-pulse">
                            <div class="w-9 h-9 rounded-full bg-surface shrink-0"></div>
                            <div class="flex-1 space-y-2 pt-1">
                                <div class="h-3 rounded bg-surface"></div>
                                <div class="h-3 w-2/3 rounded bg-surface"></div>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            {{-- Itens --}}
            <template x-for="item in items" :key="item.id">
                <button type="button" @click="openItem(item)"
                        :class="item.is_read ? 'bg-white hover:bg-surface' : 'bg-brand-orange/10 hover:bg-brand-orange/20'"
                        class="w-full text-left px-4 py-3 flex items-start gap-3 transition-colors">

                    {{-- Avatar do autor: foto, ou círculo preto com as iniciais (mesma regra do x-user-avatar) --}}
                    <template x-if="item.actor_avatar_url">
                        <img :src="item.actor_avatar_url" :alt="item.actor_name"
                             class="w-9 h-9 rounded-full object-cover shrink-0">
                    </template>
                    <template x-if="!item.actor_avatar_url && item.actor_initials">
                        <span x-text="item.actor_initials"
                              class="w-9 h-9 inline-flex items-center justify-center rounded-full bg-brand-ink text-white text-xs font-semibold shrink-0"></span>
                    </template>
                    <template x-if="!item.actor_avatar_url && !item.actor_initials">
                        <span class="w-9 h-9 inline-flex items-center justify-center rounded-full bg-steel text-white shrink-0">
                            <i class="fa-solid fa-gear text-xs"></i>
                        </span>
                    </template>

                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-brand-ink line-clamp-2"
                           :title="`${item.actor_name} ${item.action_text} ${item.card_label}`">
                            <strong class="font-semibold" x-text="item.actor_name"></strong>
                            <span x-text="item.action_text"></span>
                            <strong class="font-semibold" x-text="item.card_label"></strong>
                        </p>
                        <p class="mt-1 text-xs text-steel" x-text="item.created_at_human" :title="item.created_at_full"></p>
                    </div>

                    <span x-show="!item.is_read" class="mt-2 w-2 h-2 rounded-full bg-brand-orange shrink-0"></span>
                </button>
            </template>

            {{-- Vazio --}}
            <template x-if="!loading && loaded && items.length === 0">
                <div class="px-4 py-10 text-center">
                    <i class="fa-solid fa-bell-slash text-2xl text-hairline"></i>
                    <p class="mt-3 text-sm text-steel"
                       x-text="filter === 'nao_lidas' ? 'Tudo em dia. Nenhuma notificação não lida.' : 'Nenhuma notificação por aqui.'"></p>
                </div>
            </template>

            {{-- Carregando páginas seguintes --}}
            <template x-if="loading && items.length > 0">
                <div class="px-4 py-3 text-center text-xs text-steel">
                    <i class="fa-solid fa-circle-notch fa-spin mr-1"></i> Carregando...
                </div>
            </template>

            {{-- Fim da lista --}}
            <template x-if="!loading && !hasMore && items.length > 0">
                <div class="px-4 py-3 text-center text-xs text-steel">Você chegou ao fim.</div>
            </template>
        </div>
    </div>
</div>
