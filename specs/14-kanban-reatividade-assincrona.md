# 14 — Kanban Reativo e Carregamento Assíncrono

> **Modelo recomendado:** `opus` (Opus 4.8) — mexe no núcleo do Kanban e no ponto mais sensível
> da integração Alpine + SortableJS.
>
> Melhoria pós-entrega sobre a [Spec 07 — Kanban e Cards](07-kanban-e-cards.md). Não altera regras de
> negócio; muda **como** os dados chegam à tela e como a UI reage às mudanças.

## 1. Objetivo

Eliminar o `window.location.reload()` do fluxo do quadro e tornar a tela reativa:

1. **Sem recarregar a página** ao criar, editar, mover, concluir, transferir ou excluir um card, nem ao
   comentar/anexar. O modal fecha e o quadro reflete a mudança na hora.
2. **Carregamento desacoplado:** ao abrir `/quadros/{board}`, a página (shell: cabeçalho, barra de filtros,
   estrutura das colunas) aparece imediatamente; os **cards** são buscados numa segunda requisição, com um
   **estado de carregamento** (skeleton/spinner) enquanto chegam.

## 2. Contexto atual (o que existe hoje)

- [`app/Http/Controllers/BoardController.php::show()`](../app/Http/Controllers/BoardController.php) monta
  `$columns` (com os cards já serializados por `cardCompact()`) e injeta tudo **inline** no HTML via
  `Illuminate\Support\Js::from(...)` no `x-data="kanban({...})"`. Ou seja: os cards vêm no mesmo request da
  página e são renderizados pelo Blade (`@foreach`).
- [`resources/views/boards/show.blade.php`](../resources/views/boards/show.blade.php) renderiza cada card
  em Blade (`@foreach ($column['cards'] as $card)`) e a visão **Lista** a partir de `$allCards` (também Blade).
- [`resources/js/kanban.js`](../resources/js/kanban.js):
  - `save()`, `moveToColumn()`, `doTransfer()`, `doConclude()`, `remove()` → todos chamam a API e depois
    **`window.location.reload()`**.
  - Drag-and-drop (`persistMove`) já persiste via `POST /cards/{card}/mover` **sem reload** e atualiza os
    contadores mexendo no DOM (`refreshCounts()`), mas depende dos nós renderizados pelo Blade.
  - O painel de detalhe já é assíncrono: `openCard()` faz `GET /cards/{card}` e popula `form`.
- `cardCompact()` é um método **privado** de `BoardController`; `CardController::store()/update()` devolvem o
  JSON **completo** (`cardJson()`, formato do detalhe), que **não** tem o mesmo shape do card compacto do quadro.

**Problema:** cada alteração recarrega a página inteira (re-render de todo o quadro, refetch de tudo), o que
é lento e "pisca" a tela. E como os cards vêm embutidos no HTML, não há como mostrar a página antes deles.

## 3. Escopo

**Dentro:**
- Endpoint JSON dedicado para os dados do quadro (colunas + cards compactos), com os filtros aplicados.
- Carregamento assíncrono dos cards com estado de loading (skeleton por coluna).
- Modelo reativo único (`this.columns`) como fonte da verdade; cards renderizados no cliente (Alpine `x-for`),
  tanto na visão **Kanban** quanto na **Lista**.
- Todas as mutações (criar, editar, mover para fase, drag-and-drop, excluir, concluir, transferir, reabrir a
  partir do quadro) atualizam o modelo reativo **sem reload**; o modal fecha ao concluir a ação.
- Filtros e busca reaplicados via refetch do endpoint (sem reload da página).
- Presenter de card compacto compartilhado entre `BoardController` (lista) e `CardController` (retorno de
  store/update), garantindo shape idêntico.

**Fora (não muda agora):**
- Regras de negócio de mover/transferir/concluir (Actions permanecem iguais).
- Realtime/websocket entre usuários (segue sendo single-user; sem broadcast).
- Painel de detalhe do card continua buscando `GET /cards/{card}` sob demanda (já é assíncrono e está ok).
- Reescrita para framework SPA (Vue/React). Mantém-se Blade + Alpine.

## 4. Arquitetura proposta

### 4.1 Fonte única reativa

O componente `kanban()` passa a manter `this.columns` como **estado reativo** (array de colunas, cada uma com
`{ id, name, color, is_entry, is_final, cards: [...] }`). Tudo na tela — colunas, contadores, visão Lista —
deriva desse array. Nenhuma operação toca o DOM diretamente para inserir/remover card; só mutamos `this.columns`
e o Alpine re-renderiza.

- Contador da coluna: `column.cards.length` (reativo) — remove `refreshCounts()` e o atributo `data-count`.
- Visão Lista: `columns.flatMap(c => c.cards.map(card => ({...card, column_name, column_color})))` computado
  no cliente (getter), substituindo o `$allCards` do Blade.

### 4.2 Endpoint de dados assíncrono

Novo endpoint que devolve o mesmo conteúdo que hoje é injetado inline:

```
GET /quadros/{board}/kanban   →   boards.kanban.data
```

- Autorização: `view` no board (idêntico ao `show()`).
- Aceita os mesmos filtros de query string já usados hoje (`search`, `empresa_id`, `event_id`,
  `assignee_id`, `priority`).
- Resposta:
  ```json
  {
    "columns": [
      { "id": 1, "name": "...", "color": "#cf3f3f", "is_entry": true, "is_final": false,
        "cards": [ { /* card compacto */ } ] }
    ]
  }
  ```
- `BoardController::show()` deixa de carregar/serializar os cards: passa a renderizar só o **shell**
  (colunas vazias + metadados) e os selects de filtro. A view chama esse endpoint no `init()`.

### 4.3 Presenter de card compacto compartilhado

Extrair `cardCompact()` de `BoardController` para um ponto único reutilizável — recomendado
`app/Support/CardPresenter.php` (método estático `compact(Card $card): array`) — e usar em:
- `BoardController` (endpoint da §4.2), e
- `CardController::store()` e `::update()`, que passam a **incluir o card compacto** na resposta (ex.: chave
  `card` no JSON, ao lado do detalhe atual, ou um endpoint/记formato combinado). Assim o front insere/atualiza o
  card no `this.columns` **sem** um segundo request.

Shape compacto (o que já existe hoje em `cardCompact`): `id, title, empresa, event, assignee,
assignee_initial, assignee_avatar_url, due_date, due_status, priority, estimated_value, attachments_count,
comments_count`. Acrescentar `board_column_id` (necessário para saber em qual coluna inserir/realocar).

### 4.4 Renderização client-side (Kanban + Lista)

- Cards do Kanban passam a ser renderizados por `<template x-for="card in column.cards" :key="card.id">`,
  reproduzindo o markup atual (borda por `due_status`, badge de prioridade, avatar/iniciais, valor, contadores,
  tooltip). Os `@php match(...)` do Blade viram helpers JS (`dueBorderClass(card)`, `priorityBadgeClass(card)`).
- Visão Lista idem, via `x-for` sobre o getter de cards achatados.
- O estado de carregamento (§5) fica **por coluna**: enquanto `loadingCards === true`, cada coluna mostra 2–3
  skeletons; ao chegar a resposta, `x-for` renderiza os cards reais.

### 4.5 SortableJS + Alpine — ponto de atenção principal

SortableJS e o `x-for` do Alpine **disputam o controle do mesmo DOM** (ambos inserem/removem nós). Esse é o
maior risco técnico. Abordagem recomendada (padrão consolidado de coexistência):

1. Manter `x-for` como dono do DOM (fonte = `this.columns`).
2. No `onEnd` do Sortable, **desfazer imediatamente a mutação de DOM que o Sortable fez** (recolocar o nó na
   posição original em `evt.from`/`evt.oldIndex`) e então **mutar `this.columns`** (remover da coluna de origem,
   inserir na de destino/posição). O Alpine re-renderiza a partir do array — evitando nó duplicado/fantasma.
3. Persistir com `POST /cards/{card}/mover`; em caso de falha, reverter a mutação no array e avisar (toast).

> **Fallback**, caso a reconciliação acima se mostre instável: renderizar a lista de cards de cada coluna via
> **partial HTML** devolvido pelo endpoint (Sortable continua dono de DOM "burro") e fazer as mutações por
> injeção pontual de HTML. Mais verboso, mas elimina o conflito com `x-for`. A decisão final fica para a
> implementação, documentando o que foi escolhido.

## 5. Carregamento desacoplado (shell → loading → cards)

1. Request da página (`show`) devolve **rápido**: layout, cabeçalho, barra de filtros e as colunas **vazias**
   (com cabeçalho/cor/contador zerado) + selects de filtro. Sem cards.
2. `kanban().init()`:
   - `this.loadingCards = true`
   - `GET /quadros/{board}/kanban?<filtros>` → popula `this.columns`
   - `this.loadingCards = false` → inicializa Sortable (após o `x-for` render).
3. Enquanto `loadingCards`: skeleton nos corpos das colunas (blocos cinza com `animate-pulse`).
4. Erro no fetch: estado de erro por quadro com botão "Tentar novamente".

## 6. Fluxos reativos (sem reload)

| Ação | Hoje | Depois |
|------|------|--------|
| **Criar card** | POST + reload | POST → resposta traz card compacto → `push` na coluna alvo → fecha modal → toast |
| **Editar card** | PUT + reload | PUT → resposta traz card compacto → substitui no array (realoca se `board_column_id` mudou) → fecha modal → toast |
| **Mover para fase** (botões) | POST /mover + reload | POST → move o objeto entre arrays localmente → fecha modal → toast |
| **Drag-and-drop** | POST /mover, mexe DOM | §4.5: revert + muta array + persiste (reverte no erro) |
| **Excluir** | DELETE + reload | DELETE → remove do array → fecha modal → toast |
| **Concluir / Transferir / Reabrir** | POST + reload | POST → **remove o card do quadro** no array (ele sai deste board) → fecha modal → toast |
| **Comentar / Anexar** | já sem reload (atualiza `comments`/`attachments`) | manter; ao fechar, atualizar `comments_count`/`attachments_count` no card compacto do array |

Regra geral: a ação só é refletida no modelo **após** a resposta de sucesso da API (server-confirmed), exceto o
drag-and-drop, que é **otimista** (move na hora, reverte no erro) para manter a sensação de resposta imediata.

## 7. Filtros e busca reativos

- Trocar um filtro (empresa/evento/responsável/prioridade) ou buscar → **não** dá submit no form nem recarrega:
  chama `reloadCards()` (refetch do endpoint §4.2 com os filtros atuais) mostrando o loading.
- A busca textual usa **debounce** (~300 ms).
- O contador de "filtros ativos" e o "Limpar filtros" continuam funcionando, agora client-side.
- A URL pode ser atualizada via `history.replaceState` para manter os filtros compartilháveis/recarregáveis
  (opcional, recomendado).

## 8. Endpoints (novos / alterados)

```
GET  /quadros/{board}/kanban            boards.kanban.data   (NOVO) colunas + cards compactos, com filtros
POST /quadros/{board}/cards             cards.store          (ALT) resposta passa a incluir card compacto
PUT  /cards/{card}                      cards.update         (ALT) resposta passa a incluir card compacto
POST /cards/{card}/mover                cards.move           (mantido)
POST /cards/{card}/enviar-departamento  cards.transfer       (mantido)
POST /cards/{card}/concluir             cards.conclude       (mantido)
POST /cards/{card}/reabrir              cards.reopen         (mantido)
DELETE /cards/{card}                    cards.destroy        (mantido)
```

## 9. Mudanças no backend (arquivos)

- **`routes/web.php`** — nova rota `GET quadros/{board}/kanban` → `BoardController::kanbanData`.
- **`app/Support/CardPresenter.php`** (novo) — `compact(Card $card): array` (migra a lógica de
  `BoardController::cardCompact`, acrescenta `board_column_id`).
- **`app/Http/Controllers/BoardController.php`**:
  - `show()` deixa de eager-loadar/serializar `columns.cards`; passa só o shell (colunas sem cards) + filtros +
    selects. Remove a injeção dos cards no `x-data`.
  - novo `kanbanData(Request, Board)`: aplica os mesmos filtros e devolve `{ columns: [...] }` usando o Presenter.
- **`app/Http/Controllers/CardController.php`** — `store()`/`update()` incluem o card compacto na resposta
  (via Presenter).

## 10. Mudanças no frontend (arquivos)

- **`resources/views/boards/show.blade.php`**:
  - Cabeçalho/filtros/estrutura de colunas mantidos; corpo das colunas passa a `x-for` sobre `column.cards`
    (com skeleton em `loadingCards`). Visão Lista passa a `x-for` sobre o getter achatado.
  - Filtros: `onchange`/submit → chamadas Alpine (`reloadCards()`), busca com debounce.
- **`resources/js/kanban.js`**:
  - Estado novo: `columns` (reativo), `loadingCards`, `loadError`.
  - `init()` → `fetchCards()`; `reloadCards()` (filtros); `fetchCards()` popula `columns` e (re)inicia Sortable.
  - Reescrever `save()`, `moveToColumn()`, `doTransfer()`, `doConclude()`, `remove()`, `persistMove()` para
    mutar `columns` em vez de `window.location.reload()`.
  - Helpers de render migrados do Blade (`dueBorderClass`, `dueTooltip`, `priorityBadge`, etc.).
  - Getters `previousColumns`/`nextColumns`/`isFinalColumn` continuam válidos (já usam `cfg.columns`/`columnId`).

## 11. Estados de UI e tratamento de erro

- **Loading de cards:** skeleton por coluna (`animate-pulse`), some ao popular.
- **Erro de carregamento:** mensagem + "Tentar novamente" (refaz `fetchCards`).
- **Salvar (create/edit):** botão em estado `saving`; em erro de validação (422) o modal **permanece aberto**
  com os erros; em erro genérico, toast e modal aberto.
- **Mutações de board (mover/concluir/transferir/excluir):** toast de sucesso; em erro, toast e o modelo não muda.
- **Drag-and-drop:** otimista; reverte a posição no array em falha e mostra toast.
- Todo feedback via `window.upAlerts` (SweetAlert2), como no restante do sistema.

## 12. Plano de implementação (incremental)

Cada etapa é entregável e testável isoladamente por HTTP:

1. **Presenter + endpoint** — criar `CardPresenter`, rota/método `kanbanData`, mantendo `show()` como está
   (endpoint coexiste, ainda sem uso na tela). Validar JSON por HTTP.
2. **Carregamento assíncrono** — `show()` para de mandar cards; front busca via `fetchCards()` com skeleton;
   cards renderizados por `x-for` (Kanban + Lista). Sortable religado após render (§4.5).
3. **Mutações sem reload** — trocar cada `reload()` por mutação de `columns` (create/edit/move/delete/
   conclude/transfer). `store()/update()` passam a devolver o card compacto.
4. **Filtros/busca reativos** — `reloadCards()` no lugar do submit; debounce na busca; `replaceState` na URL.
5. **Refino** — estados de erro, `pint`, `npm run build`, verificação de N+1 no endpoint, revisão de acessos.

## 13. Critérios de aceite

- [x] Abrir `/quadros/{board}` mostra o shell imediatamente e um loading nos cards até eles chegarem.
- [x] Criar card: modal fecha e o card aparece na coluna correta **sem reload**.
- [x] Editar card (título, descrição, responsável, prazo, prioridade, valores, campos do quadro): modal fecha e
      o card reflete a mudança **sem reload**; se a coluna mudou, ele realoca.
- [x] "Mover para fase", drag-and-drop, excluir, concluir, transferir e reabrir: refletem no quadro **sem reload**.
- [x] Contadores das colunas e a visão Lista refletem as mudanças automaticamente.
- [x] Trocar filtros/buscar reaplica sem recarregar a página (com loading).
- [x] Nenhum `window.location.reload()` remanescente no fluxo do quadro.
- [x] Endpoint de dados sem N+1 (eager loading equivalente ao atual) e respeitando autorização/escopo de acesso.
- [x] `./vendor/bin/pint` limpo e `npm run build` sem erros.

> **Nota de implementação:** "reabrir" a partir do quadro não se aplica — cards concluídos saem do quadro
> (não há como reabri-los de dentro do `card-panel`); reabertura continua existindo apenas em "Todos os
> Cards" (`cards-hub.js`), que já é reativo e não foi tocado por esta spec.

## 14. Riscos e mitigações

- **SortableJS × Alpine `x-for` (principal):** ver §4.5 — padrão de "revert + mutar array"; fallback por partial
  HTML documentado. Validar bem drag entre colunas e reordenação na mesma coluna.
- **Shape divergente do card:** mitigado pelo `CardPresenter` único (um só lugar define o card compacto).
- **Perda dos filtros no reload manual do browser:** mitigado com `history.replaceState` mantendo a query string.
- **Regressão de acesso:** o endpoint novo deve repetir exatamente as checagens de `show()` (policy `view` +
  escopo por perfil) — cobrir com teste HTTP (usuário sem acesso recebe 403).
- **Flicker/estado intermediário:** server-confirmed nas mutações (exceto DnD otimista) evita estados falsos.
```
