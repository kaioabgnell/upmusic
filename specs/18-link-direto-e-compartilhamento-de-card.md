# 18 — Link Direto e Compartilhamento de Card

> **Modelo recomendado:** `sonnet` (Sonnet 5) — rota + binding + sincronização de URL, sem regra de
> negócio nova (reaproveita autorização já existente). Melhoria pós-entrega (numeração fora das fases
> 0–12, como [14](14-kanban-reatividade-assincrona.md)/[15](15-banco-de-precos-por-categoria.md)/
> [16](16-captura-rapida-orcamentos-nf.md)/[17](17-fluxo-de-aprovacao-de-etapas.md)).

## 1. Objetivo

Cada card do Kanban passa a ter uma **URL própria e compartilhável**
(`/quadros/{board}/card/{card}`). Abrir um card (clique no card do quadro) atualiza a URL do navegador
sem recarregar a página; colar essa URL diretamente no navegador abre o quadro já com o modal do card
aberto, desde que o usuário tenha permissão de acesso àquele quadro. Um item **"Compartilhar Card"** no
menu de 3 pontos do modal copia essa URL para a área de transferência.

## 2. Conceito

- **Identificação visual do card pelo ID**: no card compacto do Kanban (e na visão Lista) e no cabeçalho
  do modal, o título passa a ser exibido como **`#{id} - {título}`**. Isso só se aplica a cards **já
  criados** — no formulário de criação (`mode === 'create'`, sem `id` ainda) o cabeçalho continua
  mostrando "Novo card", sem prefixo.
- **URL reflete o card aberto, só no Kanban do quadro** (`/quadros/{board}`, `resources/js/kanban.js`) —
  não se aplica à listagem global "Todos os cards" (`cards-hub.js`), que não tem uma URL por quadro. Ao
  abrir qualquer card nessa tela (clique no Kanban, na Lista, ou reabertura via link direto), a URL do
  navegador muda para `/quadros/{board}/card/{card}` via `history.replaceState` (sem reload, sem criar
  entrada extra no histórico — mesmo mecanismo já usado por `updateUrlQueryString()`/
  `openCardFromQueryString()` no próprio `kanban.js`, ver specs/16). Ao fechar o modal (ou criar um novo
  card), a URL volta para `/quadros/{board}` (sem o card).
- **Abrir via URL direta**: acessar `/quadros/{board}/card/{card}` renderiza a mesma página do Kanban
  (`boards.show`) e, assim que os cards carregarem, abre automaticamente o modal daquele card — igual ao
  que já acontece com `?abrir_card=` vindo da Captura Rápida, mas aqui o id vem do **path**, não da
  query string, e a URL **permanece** apontando para o card (não é removida depois de abrir).
- **Permissão**: reaproveita 100% a autorização já existente — `BoardController::show()` já chama
  `$this->authorize('view', $board)`, que já barra (`403`) quem não tem acesso ao quadro. Não é criada
  nenhuma regra nova; "se tiver permissão abre o modal" já é automaticamente satisfeito, porque sem
  permissão a própria página do quadro não carrega.
- **Compartilhar Card**: novo item no menu de 3 pontos do card (`card-panel.blade.php`), **logo abaixo de
  "Arquivar"/"Desarquivar"**, acima do separador que antecede "Excluir". Ao clicar, copia
  `${origin}/quadros/{board_id}/card/{card_id}` para a área de transferência
  (`navigator.clipboard.writeText`, mesmo padrão já usado em `captures/ios-setup.blade.php` e
  `external/manage.blade.php`) e mostra o alerta de sucesso com o texto exato pedido: **"Link do card
  copiado para sua área de transferência."**. Como o modal é componente compartilhado
  (`card-panel.js`), esse botão aparece tanto no Kanban quanto em "Todos os cards" — a URL copiada é
  sempre a do Kanban (`/quadros/{board}/card/{card}`), independente de onde o modal foi aberto.

## 3. Backend

### 3.1 Rota

Reaproveita o **mesmo controller/método** `BoardController::show()` para as duas URLs — evita duplicar
toda a lógica de carregar campos/colunas/opções do quadro:

```
GET /quadros/{board}                 boards.show        (já existe)
GET /quadros/{board}/card/{card?}    boards.show.card    (nova — {card} opcional, mesmo método)
```

```php
public function show(Request $request, Board $board, CardFormOptionsService $options, ?Card $card = null)
{
    $this->authorize('view', $board);

    abort_if($card && $card->board_id !== $board->id, 404);

    // ...monta a view exatamente como já faz hoje...

    return view('boards.show', [
        // ...chaves já existentes...
        'openCardId' => $card?->id,
    ]);
}
```

`abort_if($card && $card->board_id !== $board->id, 404)`: card de outro quadro na URL (ex.: alguém edita
o id manualmente) vira 404 — mesmo padrão de checagem cruzada já usado em `CardController::move()`
(`abort_unless($column->board_id === $card->board_id, ...)`).

Nota de nomenclatura: o segmento é `card` (singular) enquanto a rota de criação já existente é
`quadros/{board}/cards` (plural, `POST`, `cards.store`) — inconsistente, mas mantido assim de propósito
porque foi o formato exato pedido (`/quadros/1/card/[ID DO CARD]`); não há colisão de rota entre os dois
porque um é `GET .../card/{id}` e o outro é `POST .../cards`.

### 3.2 View (bootstrap do Alpine)

`boards/show.blade.php` passa `openCardId` para o componente `kanban(...)`, mesma ideia de
`initialFilters` já existente:

```blade
initialOpenCardId: {{ Illuminate\Support\Js::from($openCardId) }},
```

## 4. Frontend

### 4.1 Exibição do ID (`#{id} - {título}`)

- **Card compacto do Kanban** (`boards/show.blade.php`, dentro do `x-for="card in column.cards"`):
  troca `x-text="card.title"` por algo como `x-text="'#' + card.id + ' - ' + card.title"`.
- **Visão Lista** (mesma página, tabela `x-for="card in flatCards"`): mesmo tratamento na coluna Título.
- **Cabeçalho do modal** (`card-panel.blade.php`, `h3` do cabeçalho): o `x-text` atual
  (`mode === 'create' ? 'Novo card' : (form.title || 'Card')`) ganha o prefixo só no ramo de
  visualização: `mode === 'create' ? 'Novo card' : ('#' + cardId + ' - ' + (form.title || 'Card'))`.
- Fora de escopo: a listagem global "Todos os cards" (`cards/index.blade.php`) **não** ganha o prefixo —
  ela já mostra o título em uma tabela dedicada com outras colunas (quadro, coluna, empresa...) e não foi
  pedida explicitamente; mantida como está.

### 4.2 `kanban.js` — sincronização da URL

- **Ao abrir um card** (`openCard(id)`, chamado pelo clique no card do Kanban/Lista, ou pela abertura
  automática vinda da URL): depois de carregar os dados do card, atualizar a URL para
  `/quadros/{board_id}/card/{card_id}` via `history.replaceState` — sem religar filtros já aplicados na
  query string (o board id já vem de `cfg`/da própria página, não precisa ser recalculado).
- **Ao fechar o modal** (`closePanel()`) ou **ao criar um novo card** (`openCreate()`, que não tem id
  ainda): reverter a URL para `/quadros/{board_id}` (sem o segmento `/card/...`), preservando a query
  string de filtros se houver (mesmo helper que já monta a URL de filtros hoje).
- **Abertura automática via rota** (`init()`): se `cfg.initialOpenCardId` vier preenchido, chamar
  `openCard(id)` assim que `fetchCards()` terminar — substitui/complementa o `openCardFromQueryString()`
  já existente (specs/16), que continua servindo exclusivamente o fluxo de `?abrir_card=` da Captura
  Rápida (não usa a URL de path e remove o parâmetro após abrir — comportamento diferente e que
  **não muda**).
- **Diferença importante em relação ao `openCardFromQueryString()` existente**: aquele fluxo abre o card
  e **remove** o parâmetro da URL logo em seguida (é um redirect de uso único). Este novo fluxo faz o
  oposto — a URL **permanece** apontando para o card enquanto o modal estiver aberto, porque o objetivo
  aqui é justamente ser copiável/compartilhável.

### 4.3 Menu "Compartilhar Card" (`card-panel.blade.php` + `card-panel.js`)

Novo botão no menu de 3 pontos, entre "Arquivar"/"Desarquivar" e o separador que antecede "Excluir":

```blade
<button type="button" @click="actionsMenuOpen = false; shareCard()" class="w-full text-left px-3 py-2 text-sm text-brand-ink hover:bg-surface flex items-center gap-2">
    <i class="fa-solid fa-share-nodes w-4 text-steel"></i> Compartilhar Card
</button>
```

`card-panel.js` ganha `shareCard()`:

```js
shareCard() {
    const url = `${window.location.origin}/quadros/${this.form.board_id}/card/${this.cardId}`;
    navigator.clipboard.writeText(url);
    window.upAlerts.notifySuccess('Link do card copiado para sua área de transferência.');
},
```

`form.board_id` já é preenchido em `openCard()` hoje (usado pela listagem global para buscar as colunas do
quadro do card) — não precisa de nenhum dado novo vindo do backend.

## 5. Regras de negócio / casos de borda

- Card de outro quadro na URL (`board_id` da URL ≠ `board_id` do card) → `404`.
- Card inexistente ou excluído (soft delete) na URL → `404` (comportamento padrão do route model
  binding, igual a qualquer outra rota `{card}` do sistema).
- Usuário sem acesso ao quadro → `403` (comportamento já existente de `authorize('view', $board)`,
  nenhuma mudança).
- Card arquivado ou concluído continua abrindo normalmente pelo link direto — o link não depende do
  card estar visível no board naquele momento, só de existir e pertencer ao quadro da URL.
- "Compartilhar Card" só aparece para card já existente (`mode === 'view' && cardId`) — mesma condição
  que já esconde o menu inteiro de 3 pontos na criação; nenhuma checagem adicional necessária.

## 6. Suposições assumidas (confirmar se algo estiver errado)

- **`history.replaceState`, não `pushState`** — a URL reflete o card aberto, mas abrir/fechar um card não
  cria entradas no histórico do navegador (o botão "voltar" não fecha o modal). Pedido original só fala em
  copiar/colar a URL, não em navegação por histórico; se o comportamento de "voltar fecha o modal" for
  desejado, precisa de um listener de `popstate` a mais, não incluído aqui.
- **Fora de escopo**: aplicar o mesmo link direto/URL sincronizada em "Todos os cards" — pedido foi
  explicitamente "no kanbam". O botão "Compartilhar Card" continua funcionando lá (é o mesmo modal), só a
  troca de URL da própria página não acontece nessa tela.

## 7. Rotas

```
GET /quadros/{board}/card/{card?}   boards.show.card   (mesmo controller/método de boards.show)
```

## 8. Critérios de aceite

- [x] Card compacto do Kanban e da Lista exibem `#{id} - {título}`; cabeçalho do modal também, só ao
      visualizar um card existente (não na criação).
- [x] Abrir qualquer card no Kanban atualiza a URL para `/quadros/{board}/card/{card}` sem reload.
- [x] Fechar o modal (ou abrir "Novo card") volta a URL para `/quadros/{board}`.
- [x] Colar `/quadros/{board}/card/{card}` no navegador abre o quadro com o modal do card já aberto.
- [x] Card de outro quadro ou inexistente na URL retorna `404`; usuário sem acesso ao quadro recebe `403`
      (comportamento já existente, sem regressão).
- [x] Menu de 3 pontos do card ganha "Compartilhar Card" logo abaixo de Arquivar/Desarquivar.
- [x] Clicar em "Compartilhar Card" copia a URL correta para a área de transferência e mostra o alerta
      "Link do card copiado para sua área de transferência."

> **Nota sobre validação**: 404/403/200 e o valor de `initialOpenCardId` injetado na página foram
> confirmados por HTTP real (curl + sessão). A sincronização de URL no clique/fechar e o botão
> "Compartilhar Card" foram implementados seguindo o padrão já comprovado de `openCardFromQueryString()`
> e revisados linha a linha, mas **não foram exercitados num navegador real** — a pedido explícito do
> usuário nesta tarefa, sem Puppeteer/Playwright/teste visual.
