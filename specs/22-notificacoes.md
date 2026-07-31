# 22 — Notificações (sino da topbar)

> **Modelo recomendado:** `opus` (Opus 5) — tabela nova + observer no ciclo de vida do card + componente
> Alpine com scroll infinito e contador em polling. Melhoria pós-entrega (numeração fora das fases 0–12,
> como [14](14-kanban-reatividade-assincrona.md)…[21](21-modulo-licitacoes.md)).

## 1. Objetivo

Dar ao usuário um canal interno de avisos sobre as atividades em que ele foi envolvido. Um **sino na
topbar** exibe o **contador de notificações não lidas** do usuário logado; ao clicar, abre um painel
com as notificações **não lidas e lidas**, carregadas **de 10 em 10 com scroll infinito**, com um
filtro para exibir **somente as não lidas**.

O evento coberto nesta entrega é a **atribuição de responsável em um card**: quando alguém define ou
troca o responsável de um card, o **novo responsável** recebe:

> **Kaio Gomes** te colocou como responsável do card **#123 - Locação de som — Festa Junina**

Clicar na notificação **marca como lida** e leva o usuário direto ao card
(`/quadros/{board}/card/{card}`, rota já existente da [specs/18](18-link-direto-e-compartilhamento-de-card.md),
que abre o quadro com o modal do card aberto).

## 2. Conceito

- **Uma notificação = um destinatário.** Não existe notificação "para vários" — se o mesmo evento
  precisar avisar N pessoas, são N linhas. Isso mantém `read_at` por pessoa sem tabela pivô.
- **Registro histórico, não um "estado".** A notificação guarda um snapshot do que aconteceu no
  momento em que aconteceu (título do card, id do quadro). Se o card mudar de título depois, a
  notificação continua descrevendo o fato como ele foi — mesmo princípio já usado em `card_movements`.
- **Nunca notificar a si mesmo.** Se o autor da ação é a própria pessoa atribuída (o caso mais comum:
  o usuário se coloca como responsável do próprio card), nenhuma notificação é criada.
- **Só chega o que ainda dá para abrir.** A listagem e o contador aplicam a mesma visibilidade do
  Kanban (acesso ao quadro + escopo por evento da [specs/20](20-coordenador-por-evento.md)). Card
  excluído, quadro sem acesso ou evento fora do escopo → a notificação simplesmente não aparece.
  Isso evita link morto/403 a partir do sino, e faz o badge bater exatamente com a lista.
- **Ser responsável basta.** O select de responsável oferece **todos** os usuários ativos, sem filtrar
  por quadro — então dá para colocar alguém de outro departamento como responsável. Para esse caso não
  virar um responsável mudo (notificação suprimida e card inacessível), o **responsável atual sempre
  lê o próprio card e o quadro dele**, mesmo sem vínculo em `user_board`. Ver §4.5.
- **Sem WebSocket e sem fila.** `BROADCAST_DRIVER=log` e `QUEUE_CONNECTION=sync` no `.env` — a
  gravação é síncrona (uma linha por atribuição, custo desprezível) e a atualização do badge é por
  **polling leve** (endpoint que devolve só um inteiro), pausado quando a aba está em segundo plano.
- **Extensível por tipo.** O tipo é um enum (`card_assigned` hoje); novos eventos entram como novos
  valores + novo template de texto, sem tocar em schema.

## 3. Modelo de dados

### 3.1 Tabela `user_notifications`

> **Por que não `notifications`:** `App\Models\User` já usa o trait `Notifiable` do Laravel, que
> define a relação `notifications()` apontando para a tabela `notifications` com o schema do canal
> `database` (uuid + morph + `data` json). Criar uma tabela `notifications` com schema próprio
> quebraria essa relação silenciosamente. Nome dedicado evita a colisão.

`database/migrations/2026_07_30_000001_create_user_notifications_table.php`:

```php
Schema::create('user_notifications', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();   // destinatário
    $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete(); // quem agiu
    $table->foreignId('card_id')->nullable()->constrained()->cascadeOnDelete();
    $table->foreignId('board_id')->nullable()->constrained()->cascadeOnDelete();
    $table->string('type', 40);                 // App\Domain\Enums\NotificationType
    $table->json('data')->nullable();           // snapshot: card_title, actor_name
    $table->timestamp('read_at')->nullable();
    $table->timestamps();

    // Lista do sino: user_id + ordem por id desc; o read_at cobre o filtro "somente não lidas"
    // e o contador do badge sem varrer a tabela inteira.
    $table->index(['user_id', 'read_at', 'id']);
    $table->index(['user_id', 'id']);
});
```

- `card_id` / `board_id` com `cascadeOnDelete`: card **hard-deleted** leva junto as notificações
  órfãs. O `Card` usa `SoftDeletes`, então o fluxo normal (excluir pelo modal) **não** dispara o
  cascade — o soft delete é tratado na consulta (§4.3), não no banco.
- `actor_id` nullable + `nullOnDelete`: ação originada fora de uma sessão (import de template
  agendado, formulário externo) ou usuário removido → renderiza como "Sistema".
- `data` guarda o snapshot mínimo: `{"card_title": "...", "actor_name": "..."}`. `card_title` é o
  texto exibido; `actor_name` é apenas fallback caso o usuário tenha sido apagado.

### 3.2 Enum `App\Domain\Enums\NotificationType`

```php
enum NotificationType: string
{
    case CardAssigned = 'card_assigned';

    /** Texto exibido no painel (o nome do autor e o "#id - título" entram como <strong> no front). */
    public function label(): string
    {
        return match ($this) {
            self::CardAssigned => 'te colocou como responsável do card',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::CardAssigned => 'fa-solid fa-user-check',
        };
    }
}
```

### 3.3 Model `App\Models\UserNotification`

```php
class UserNotification extends Model
{
    protected $fillable = ['user_id', 'actor_id', 'card_id', 'board_id', 'type', 'data', 'read_at'];

    protected $casts = ['data' => 'array', 'type' => NotificationType::class, 'read_at' => 'datetime'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    // withTrashed: o autor pode ter sido desativado/removido depois; o histórico continua legível.
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id')->withTrashed(); }

    public function card(): BelongsTo { return $this->belongsTo(Card::class); }

    public function scopeUnread($q) { return $q->whereNull('read_at'); }
}
```

Em `App\Models\User`, adicionar:

```php
public function userNotifications(): HasMany
{
    return $this->hasMany(UserNotification::class)->latest('id');
}
```

## 4. Backend

### 4.1 Gatilho — `CardObserver`

O responsável é gravado em **cinco** caminhos diferentes hoje (`CreateCard`, `UpdateCard`,
`DuplicateCard`, `ImportTemplate` via `default_assignee_id`, e futuras origens como a Captura Rápida).
Espalhar a chamada por todos eles convida a esquecer um. O gatilho é um **observer no model `Card`**,
que pega qualquer escrita em `assignee_id` por construção:

`app/Observers/CardObserver.php`:

```php
class CardObserver
{
    public function __construct(private NotifyCardAssigned $notify) {}

    public function created(Card $card): void
    {
        if ($card->assignee_id) {
            $this->notify->execute($card, $card->assignee_id);
        }
    }

    public function updated(Card $card): void
    {
        // wasChanged() cobre tanto "não tinha responsável e ganhou" quanto "trocou de responsável".
        if ($card->wasChanged('assignee_id') && $card->assignee_id) {
            $this->notify->execute($card, $card->assignee_id);
        }
    }
}
```

Registrado em `AppServiceProvider::boot()`: `Card::observe(CardObserver::class);`

`app/Actions/Notifications/NotifyCardAssigned.php`:

```php
class NotifyCardAssigned
{
    public function execute(Card $card, int $assigneeId): ?UserNotification
    {
        $actor = auth()->user();

        // Auto-atribuição não gera aviso — a pessoa acabou de fazer a ação.
        if ($actor && $actor->id === $assigneeId) {
            return null;
        }

        // Usuário inativo/removido não acumula notificação.
        $assignee = User::where('active', true)->find($assigneeId);
        if (! $assignee) {
            return null;
        }

        return UserNotification::create([
            'user_id' => $assigneeId,
            'actor_id' => $actor?->id,
            'card_id' => $card->id,
            'board_id' => $card->board_id,
            'type' => NotificationType::CardAssigned,
            'data' => [
                'card_title' => $card->title,
                'actor_name' => $actor?->name,
            ],
        ]);
    }
}
```

**Idempotência:** trocar A → B → A gera duas notificações para A. Isso é correto — são dois eventos
distintos e a segunda pode ser a única não lida. Não há deduplicação.

### 4.2 Escopo de visibilidade — `VisibleNotifications`

Serviço único usado pelos **três** endpoints (lista, contador, marcar todas), para que badge e lista
nunca divirjam:

`app/Services/VisibleNotificationsQuery.php`:

```php
public function for(User $user): Builder
{
    return UserNotification::query()
        ->where('user_notifications.user_id', $user->id)
        // Só notificações cujo card ainda existe (não soft-deleted) e que o usuário pode abrir.
        ->whereHas('card', fn ($q) => $q->visibleTo($user)
            ->when(! $user->isAdmin() && ! $user->isCoordenador(),
                fn ($c) => $c->whereIn('board_id', $user->boards()->pluck('boards.id'))
            )
        );
}
```

- `Card::scopeVisibleTo()` (já existe, specs/20) aplica o escopo por evento do coordenador restrito.
- `whereHas('card')` já exclui card soft-deleted (o global scope do `SoftDeletes` vale dentro do
  subquery), resolvendo o link morto sem coluna extra.
- Admin e Coordenador acessam todos os quadros (`User::canAccessBoard()`), por isso a restrição de
  `board_id` só se aplica ao papel `usuario`.

### 4.3 Endpoints

`app/Http/Controllers/NotificationController.php` — todos devolvem **JSON**, todos escopados ao
`auth()->user()` (nenhum recebe `user_id` por parâmetro):

#### `GET /notificacoes` → `notifications.index`

Parâmetros:

| Parâmetro | Valores | Padrão | Efeito |
|---|---|---|---|
| `filtro` | `todas` \| `nao_lidas` | `todas` | `nao_lidas` aplica `->unread()` |
| `antes` | id inteiro | — | Página seguinte: `where('id', '<', $antes)` |

```php
public function index(Request $request, VisibleNotificationsQuery $visible)
{
    $user = $request->user();

    $items = $visible->for($user)
        ->when($request->query('filtro') === 'nao_lidas', fn ($q) => $q->unread())
        ->when($request->integer('antes'), fn ($q, $id) => $q->where('user_notifications.id', '<', $id))
        ->with(['actor:id,name,avatar_path'])
        ->orderByDesc('user_notifications.id')
        ->limit(self::PER_PAGE + 1)   // PER_PAGE = 10; o 11º só responde "tem mais?"
        ->get();

    $hasMore = $items->count() > self::PER_PAGE;
    $items = $items->take(self::PER_PAGE);

    return response()->json([
        'items' => $items->map(fn ($n) => NotificationPresenter::item($n))->values(),
        'next_cursor' => $hasMore ? $items->last()->id : null,
        'unread_count' => $visible->for($user)->unread()->count(),
    ]);
}
```

**Paginação por cursor (keyset), não `?page=`.** Com offset, uma notificação nova chegando durante a
rolagem empurra a lista e a página 2 repete o último item da página 1. Como `id` é sequencial e a
ordem é `id DESC`, o cursor `antes=<id>` é estável e usa o índice `(user_id, id)`.

#### `GET /notificacoes/contador` → `notifications.count`

```php
return response()->json(['unread_count' => $visible->for($request->user())->unread()->count()]);
```

Endpoint separado e mínimo porque é o que roda em polling; carregar a lista inteira a cada 60s seria
desperdício.

#### `POST /notificacoes/{notification}/lida` → `notifications.read`

Marca uma como lida (idempotente — já lida devolve 200 sem reescrever `read_at`). Autorização:
`abort_unless($notification->user_id === $request->user()->id, 403)` — notificação é sempre pessoal,
não há Policy nem exceção para admin. Devolve `{'unread_count': N}` para o front atualizar o badge
sem uma segunda chamada.

#### `POST /notificacoes/marcar-todas-lidas` → `notifications.read-all`

`$visible->for($user)->unread()->update(['read_at' => now()])`. Usa o mesmo escopo de visibilidade:
"marcar todas" limpa exatamente o que o badge está contando. Devolve `{'unread_count': 0}`.

### 4.4 `NotificationPresenter`

`app/Support/NotificationPresenter.php` — mesmo padrão de `CardPresenter`, um único shape para o front:

```php
public static function item(UserNotification $n): array
{
    return [
        'id' => $n->id,
        'type' => $n->type->value,
        'icon' => $n->type->icon(),
        'actor_name' => $n->actor?->name ?? ($n->data['actor_name'] ?? 'Sistema'),
        'actor_initials' => $n->actor?->initials(),          // null → ícone de engrenagem no front
        'actor_avatar_url' => $n->actor?->avatar_url,        // null → círculo com iniciais
        'action_text' => $n->type->label(),                  // "te colocou como responsável do card"
        'card_label' => '#'.$n->card_id.' - '.($n->data['card_title'] ?? ''),
        'url' => route('boards.show.card', ['board' => $n->board_id, 'card' => $n->card_id]),
        'is_read' => $n->read_at !== null,
        'created_at_human' => $n->created_at->diffForHumans(),   // locale pt_BR (config/app.php)
        'created_at_full' => $n->created_at->format('d/m/Y H:i'), // title do elemento
    ];
}
```

O texto é montado no front a partir de três partes (`actor_name` + `action_text` + `card_label`) e
não de uma string pronta, para que o nome e o `#id - título` possam ir em `<strong>` sem que o
backend devolva HTML.

### 4.5 Leitura garantida ao responsável

O select "Adicionar responsável" (`CardFormOptionsService::globalOptions()`) lista **todos** os
usuários ativos, sem filtrar por acesso ao quadro. Sem tratamento, atribuir alguém de outro
departamento produzia um responsável que não recebia a notificação (o filtro de §4.2 a escondia) e
que também não conseguia abrir o card (403). Regra adotada:

```php
// CardPolicy::view — o responsável atual lê o próprio card...
$canRead = $user->canAccessBoard($card->board) || $card->assignee_id === $user->id;
return $canRead && $this->withinEventScope($user, $card);

// BoardPolicy::view — ...e o quadro dele, para o link /quadros/{board}/card/{card} abrir.
return $user->canAccessBoard($board) || $user->isAssignedOnBoard($board);
```

E o filtro de §4.2 ganha o mesmo `or`:

```php
$query->where(fn ($q) => $q
    ->whereIn('board_id', $user->boards()->select('boards.id'))
    ->orWhere('assignee_id', $user->id));
```

Limites deliberados dessa liberação:

- **Só leitura.** `User::isAssignedOnBoard()` é um método **novo e separado** — `canAccessBoard()`
  não muda, e continua sendo a régua de escrita (`CardPolicy::update/delete/create`, destino de
  transferência/reabertura, confirmação de captura). Responsável sem acesso ao quadro **abre** o card
  mas recebe 403 ao salvar, mover ou excluir.
- **Não entra no menu.** `BoardController::index` tem consulta própria (`whereHas('users')`), então o
  quadro não passa a aparecer na lista de quadros da pessoa — ela só consegue chegar nele pelo link.
- **O escopo por evento continua absoluto.** Ser responsável não fura a restrição da specs/20: um
  coordenador restrito a eventos não vê card de outro evento nem sendo o responsável.
- **Vale para o responsável atual.** Trocado o responsável, quem saiu perde tanto a notificação
  (some da lista) quanto o acesso — coerente com "só aparece o que ainda dá para abrir".

## 5. Frontend

### 5.1 Sino na topbar

`resources/views/layouts/app.blade.php` — dentro do `<header>`, **imediatamente à esquerda** do
`x-dropdown` do menu do usuário:

```blade
<x-notification-bell />
```

O componente só entra no layout `app.blade.php` (área autenticada). `guest.blade.php` e
`public.blade.php` (formulário externo e minuta do fornecedor) **não** recebem o sino.

`resources/views/components/notification-bell.blade.php`:

```blade
<div x-data="notifications({ initialUnread: {{ $unreadCount }} })" class="relative" @click.outside="open = false">
    <button type="button" @click="toggle()" class="relative text-steel hover:text-brand-ink w-9 h-9 flex items-center justify-center rounded-full hover:bg-surface transition-colors" aria-label="Notificações">
        <i class="fa-solid fa-bell text-lg"></i>
        <span x-show="unreadCount > 0" x-cloak
              x-text="unreadCount > 99 ? '99+' : unreadCount"
              class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-brand-orange text-brand-ink text-[10px] font-bold flex items-center justify-center"></span>
    </button>
    {{-- painel: ver 5.3 --}}
</div>
```

- Badge em **laranja da marca** (`bg-brand-orange`, `#ff8c1e`) com texto preto — o accent é reservado
  a CTAs e estados ativos, e o contador é exatamente isso ([DESIGN.md](../DESIGN.md) / specs/02).
- Ícone Font Awesome (`fa-bell`), **sem emoji**, conforme as regras obrigatórias do projeto.
- `$unreadCount` vem de um **View Composer** (`app/View/Composers/NotificationComposer.php`) ligado a
  `components.notification-bell`, para que o badge já apareça correto no primeiro paint (sem "piscar"
  em 0 até o primeiro fetch). Uma query `COUNT` indexada por request na área autenticada.

### 5.2 `resources/js/notifications.js` (Alpine)

Registrado em `resources/js/app.js`: `Alpine.data('notifications', notifications);`

```js
export default function notifications({ initialUnread = 0 } = {}) {
    return {
        open: false,
        items: [],
        unreadCount: initialUnread,
        filter: 'todas',          // 'todas' | 'nao_lidas'
        cursor: null,
        hasMore: true,
        loading: false,
        loaded: false,
        pollId: null,

        init() { this.startPolling(); },

        toggle() {
            this.open = !this.open;
            // Recarrega a cada abertura. Reaproveitar o que estava em memória deixava a lista
            // velha: notificação chegada depois da 1ª abertura só aparecia com F5.
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
            if (reset) { this.items = []; this.cursor = null; this.hasMore = true; this.$refs.list?.scrollTo(0, 0); }

            const params = new URLSearchParams({ filtro: this.filter });
            if (this.cursor) params.set('antes', this.cursor);

            const res = await fetch(`/notificacoes?${params}`, { headers: { Accept: 'application/json' } });
            const data = await res.json();

            this.items.push(...data.items);
            this.cursor = data.next_cursor;
            this.hasMore = data.next_cursor !== null;
            this.unreadCount = data.unread_count;
            this.loading = false;
            this.loaded = true;
        },

        // Scroll infinito: dispara a próxima página ~80px antes do fim, para o carregamento
        // acontecer sem o usuário bater no fundo da lista.
        onScroll(e) {
            const el = e.target;
            if (el.scrollHeight - el.scrollTop - el.clientHeight < 80) this.load();
        },

        async openItem(item) {
            if (!item.is_read) await this.markRead(item, { silent: true });
            window.location.href = item.url;
        },

        async markRead(item, { silent = false } = {}) {
            if (item.is_read) return;
            item.is_read = true;                                  // otimista
            const res = await fetch(`/notificacoes/${item.id}/lida`, { method: 'POST', headers: this.headers() });
            const data = await res.json();
            this.unreadCount = data.unread_count;
            // No filtro "não lidas", o item some da lista depois de lido.
            if (!silent && this.filter === 'nao_lidas') {
                this.items = this.items.filter((i) => i.id !== item.id);
            }
        },

        async markAllRead() {
            const res = await fetch('/notificacoes/marcar-todas-lidas', { method: 'POST', headers: this.headers() });
            this.unreadCount = (await res.json()).unread_count;
            this.items.forEach((i) => { i.is_read = true; });
            if (this.filter === 'nao_lidas') this.items = [];
        },

        // Polling do contador: 60s, pausado com a aba em segundo plano e retomado no foco
        // (o usuário que volta para a aba vê o número atualizado na hora).
        startPolling() {
            this.pollId = setInterval(() => { if (!document.hidden) this.refreshCount(); }, 60000);
            document.addEventListener('visibilitychange', () => { if (!document.hidden) this.refreshCount(); });
        },

        async refreshCount() {
            const res = await fetch('/notificacoes/contador', { headers: { Accept: 'application/json' } });
            this.unreadCount = (await res.json()).unread_count;
        },

        headers() {
            return {
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            };
        },
    };
}
```

### 5.3 Painel

Ancorado ao sino (`absolute right-0 mt-2`), largura `w-96` (mobile: `w-[calc(100vw-2rem)]`), sombra e
`ring-1 ring-black/5` — mesma linguagem visual do `x-dropdown` já existente, mas com markup próprio
porque precisa de `x-ref="list"`, `@scroll` e altura fixa.

```
┌──────────────────────────────────────────────┐
│ Notificações            Marcar todas como lidas│  ← cabeçalho fixo
│ [ Todas ] [ Não lidas ]                        │  ← abas (pílulas)
├──────────────────────────────────────────────┤
│ ● (KG) Kaio Gomes te colocou como responsável │  ← fundo laranja claro = não lida
│        do card #123 - Locação de som          │
│        há 5 minutos                            │
├──────────────────────────────────────────────┤
│   (MS) Maria Souza te colocou como responsável│  ← lida: fundo branco
│        do card #118 - Contratação de buffet   │
│        há 2 horas                              │
│                    ...                         │
│              [ carregando... ]                 │  ← spinner no fim da lista
└──────────────────────────────────────────────┘
```

- **Área rolável:** `<div x-ref="list" @scroll.passive="onScroll($event)" class="max-h-[70vh] overflow-y-auto">`.
- **Item:** `<button>` de largura total (não `<a>`, porque a navegação passa por `openItem()` para
  marcar como lida antes) com `@click="openItem(item)"`.
- **Avatar:** mesma regra do `x-user-avatar` (foto se houver, senão círculo preto com as iniciais),
  reproduzida em Alpine porque o item vem de JSON:

```blade
<template x-if="item.actor_avatar_url">
    <img :src="item.actor_avatar_url" :alt="item.actor_name" class="w-9 h-9 rounded-full object-cover shrink-0">
</template>
<template x-if="!item.actor_avatar_url">
    <span x-text="item.actor_initials || '?'"
          class="w-9 h-9 inline-flex items-center justify-center rounded-full bg-brand-ink text-white text-xs font-semibold shrink-0"></span>
</template>
```

- **Texto:** `<strong x-text="item.actor_name"></strong> <span x-text="item.action_text"></span>
  <strong x-text="item.card_label"></strong>` → renderiza exatamente
  **"Kaio Gomes te colocou como responsável do card #123 - Locação de som"**. Título longo trunca em
  duas linhas (`line-clamp-2`), com o texto completo no `title` do elemento.
- **Não lida:** fundo `bg-brand-orange/10` + ponto `w-2 h-2 rounded-full bg-brand-orange` à direita.
  **Lida:** fundo branco, sem ponto, texto do horário em `text-steel`.
- **Estados:**
  - carregando a 1ª página → 3 skeletons;
  - vazio em "Todas" → ícone `fa-bell-slash` + "Nenhuma notificação por aqui.";
  - vazio em "Não lidas" → "Tudo em dia. Nenhuma notificação não lida.";
  - fim da lista → "Você chegou ao fim." quando `!hasMore && items.length > 0`.
- **"Marcar todas como lidas"** só aparece quando `unreadCount > 0`. É ação em massa e não
  destrutiva/irreversível de dados — vai direto, **sem** confirmação SweetAlert2. Erro de rede em
  qualquer chamada do painel usa `window.upAlerts.notifyError(...)` (regra do projeto: todo feedback
  passa por SweetAlert2).

## 6. Regras de negócio e casos de borda

| Situação | Comportamento |
|---|---|
| Usuário se coloca como responsável do próprio card | Nenhuma notificação |
| Responsável trocado de A para B | Só B é notificado (remoção de A fora de escopo — §8) |
| Responsável removido (`assignee_id` → `null`) | Nenhuma notificação |
| Card criado já com responsável | Notifica o responsável (observer `created`) |
| Card duplicado com responsável | Notifica — a duplicação atribui trabalho de fato |
| Import de template com `default_assignee_id` | Notifica cada responsável padrão, um card por vez |
| Responsável inativo (`active = false`) | Nenhuma notificação criada |
| Card criado sem sessão (formulário externo, captura) | `actor_id = null` → exibe "Sistema" |
| Card soft-deleted depois | Some da lista e do contador (`whereHas('card')`) |
| Card arquivado ou concluído | **Continua** aparecendo — o link ainda abre o card (specs/18) |
| Responsável sem acesso ao quadro do card | **Recebe e abre** — ser o responsável atual dá leitura do card e do quadro (§4.5); salvar/mover continua 403 |
| Usuário perdeu acesso ao quadro (e não é responsável) | Some da lista e do contador |
| Responsável foi trocado | Quem saiu perde a notificação e o acesso; quem entrou recebe a sua |
| Coordenador restrito a eventos (specs/20) | Só vê notificações de cards dos eventos permitidos |
| Marcar como lida notificação de outro usuário | `403` |
| Notificação já lida marcada de novo | `200`, `read_at` preservado (não reescreve) |
| Clique com o painel filtrado em "não lidas" | Marca como lida e navega; o item sai da lista |

**Retenção:** comando `php artisan notificacoes:limpar` (`app/Console/Commands/PruneNotifications.php`)
apaga notificações **lidas** com mais de 90 dias. Não é agendado por padrão (o projeto não roda
`schedule:work`); fica disponível para execução manual ou cron do servidor.

## 7. Rotas

Dentro do grupo `Route::middleware(['auth', 'active'])` de `routes/web.php`:

```php
// Notificações (specs/22) — JSON, sempre escopadas ao usuário logado.
Route::get('notificacoes', [NotificationController::class, 'index'])->name('notifications.index');
Route::get('notificacoes/contador', [NotificationController::class, 'count'])->name('notifications.count');
Route::post('notificacoes/{notification}/lida', [NotificationController::class, 'read'])->name('notifications.read');
Route::post('notificacoes/marcar-todas-lidas', [NotificationController::class, 'readAll'])->name('notifications.read-all');
```

Rotas literais (`contador`, `marcar-todas-lidas`) antes do wildcard `{notification}` — mesmo cuidado
já adotado nas rotas de quadros.

## 8. Fora de escopo (e por quê)

- **Aviso de remoção de responsável** ("Fulano te removeu como responsável"). O pedido descreve um
  único texto, o de atribuição. O enum e o observer já comportam o caso: bastaria um
  `card_unassigned` no `updated()` usando `getOriginal('assignee_id')`.
- **Outros eventos** (comentário no card, movimentação de coluna, prazo vencendo, aprovação/reprovação
  de etapa da specs/17). A estrutura suporta; os textos e gatilhos não foram pedidos.
- **Notificação por e-mail ou WhatsApp.** Apenas o sino in-app.
- **Tempo real (WebSocket/Echo).** Exigiria broadcaster e worker; a infra atual é `sync`/`log`.
  O polling de 60s é o substituto.
- **Preferências por usuário** (silenciar tipos de notificação).
- **Abrir o card sem recarregar quando já se está no quadro certo.** `openItem()` navega sempre por
  `window.location.href` — a rota da specs/18 já abre o modal do card ao carregar a página, e evitar
  o reload exigiria acoplar o sino ao componente `kanban`.

## 9. Testes (`php artisan test`)

Feature (`tests/Feature/Notifications/`):

1. Atribuir responsável a um card cria notificação para o novo responsável, com `type`,
   `card_id`, `board_id`, `actor_id` e snapshot do título corretos.
2. Auto-atribuição **não** cria notificação.
3. Trocar A → B cria uma notificação para B e nenhuma nova para A.
4. Criar card já com responsável notifica.
5. Responsável inativo não recebe notificação.
6. `GET /notificacoes` devolve no máximo 10 itens e `next_cursor`; a segunda página com `antes=<id>`
   traz os 10 seguintes sem repetir nem pular item.
7. `next_cursor` vem `null` na última página.
8. `filtro=nao_lidas` devolve só as com `read_at` nulo.
9. `unread_count` bate com o número de não lidas visíveis.
10. Card soft-deleted some da lista e do contador.
11. Usuário `usuario` sem acesso ao quadro não vê a notificação daquele card; admin/coordenador veem.
12. Coordenador restrito por evento (specs/20) não vê notificação de card de evento fora do escopo.
13. `POST /notificacoes/{id}/lida` marca como lida, devolve `unread_count` atualizado e é idempotente.
14. Marcar notificação de outro usuário → `403`.
15. `POST /notificacoes/marcar-todas-lidas` zera o contador e não toca em notificações de outros.
16. Rotas exigem autenticação (visitante → redirect para login).

Unit (`tests/Unit/`): `NotificationPresenter` monta `card_label` como `#{id} - {título}` e cai para
"Sistema" quando `actor_id` é nulo.

> Conforme as convenções do projeto, **não** implementar testes de front, e2e, Playwright ou carga.

## 10. Critérios de aceite

- [x] Sino Font Awesome na topbar de toda a área autenticada, à esquerda do menu do usuário.
- [x] Badge laranja com o total de não lidas do usuário logado, correto já no primeiro carregamento
      da página e atualizado por polling (60s) e ao voltar o foco para a aba; exibe `99+` acima de 99
      e some quando zero.
- [x] Definir ou trocar o responsável de um card gera notificação para o novo responsável; colocar a
      si mesmo não gera nada.
- [x] O item exibe foto do autor (ou círculo com as iniciais) e o texto
      **"{Autor} te colocou como responsável do card #{id} - {título}"**, com o horário relativo.
- [x] O painel lista não lidas e lidas, visualmente distintas (não lida com fundo laranja claro e
      marcador).
- [x] Carrega 10 por vez, com novas páginas carregadas automaticamente ao rolar até o fim, sem
      repetir nem pular itens quando chegam notificações durante a rolagem.
- [x] Filtro "Não lidas" mostra apenas as não lidas; "Todas" volta à lista completa.
- [x] Clicar em uma notificação a marca como lida (badge cai na hora) e abre
      `/quadros/{board}/card/{card}` com o modal do card já aberto.
- [x] "Marcar todas como lidas" zera o badge e o estado visual de todos os itens carregados.
- [x] Notificação de card excluído, de quadro sem acesso ou de evento fora do escopo do coordenador
      restrito não aparece na lista nem no contador.
- [x] Tentar marcar como lida a notificação de outro usuário retorna `403`.
- [x] `php artisan test` verde, `./vendor/bin/pint --dirty` e `npm run build` limpos.

> **Nota sobre validação**: os endpoints, o gatilho, o escopo de visibilidade, a paginação por cursor,
> o 403 e o badge no HTML foram confirmados por **HTTP real** (curl + sessões de admin e de usuário
> comum) e por 27 testes automatizados. A interação do painel em si — abrir o sino, rolar até o fim
> para carregar a próxima página, alternar as abas — foi implementada seguindo os padrões Alpine já
> usados no projeto e revisada linha a linha, mas **não foi exercitada num navegador real**, conforme
> a convenção do projeto de não usar Playwright/Puppeteer/teste visual.
