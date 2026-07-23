# 20 — Coordenador Restrito por Evento

> **Modelo recomendado:** `opus` (Opus 4.8) — mexe em autorização, escopo de consultas e visibilidade de
> menus em várias telas (transversal, alta complexidade). Melhoria pós-entrega (numeração fora das fases
> 0–12, como [14](14-kanban-reatividade-assincrona.md)…[19](19-formulario-de-minuta-do-fornecedor.md)).

## 1. Objetivo

Hoje há 3 perfis ([04](04-autenticacao-e-permissoes.md)): **Usuário**, **Coordenador** e **Admin**.
Admin e Coordenador enxergam tudo. A Up Music precisa de **coordenadores que só enxergam eventos
específicos**: o "Coordenador X" vê apenas dados do "Evento XPTO" (e pode ter mais de um evento vinculado).

Esta spec adiciona a capacidade de **vincular eventos a um coordenador no cadastro** e, quando ele estiver
logado, **restringir tudo que ele vê** (menus, painel, cards, filtros, listas de usuários/eventos) aos
eventos aos quais está vinculado — sem afetar Admins nem coordenadores que não tenham nenhum evento
vinculado.

## 2. Conceito

- **Opt-in por coordenador** (backward-compatible): a restrição vale para o coordenador **que tiver ao
  menos um evento vinculado**. Coordenador **sem** evento vinculado continua vendo tudo (comportamento
  atual). Admin **nunca** é restringido. Chamamos o primeiro caso de **coordenador restrito por evento**.
- **Vínculo N:N**: um coordenador pode ter vários eventos; um evento pode ter vários coordenadores. Nova
  tabela pivot `event_user`.
- **"Ver dados de um evento" = ver os cards daquele evento** (`cards.event_id`). Toda a filtragem de cards
  (painel, quadros, "Todos os cards", Kanban) passa a considerar o conjunto de `event_id` permitidos do
  coordenador restrito.
- **Cards sem evento** (`event_id = null`): **não** aparecem para um coordenador restrito — ele só vê
  cards cujo `event_id` está na sua lista (ver §8, decisão em aberto: como tratar cards órfãos).
- **Enforcement no servidor, não só na UI**: esconder o item de menu é a camada visual; o bloqueio real é
  por Policy/escopo de consulta, para que acesso direto por URL também seja barrado.

## 3. Modelo de dados

### 3.1 `event_user` (nova tabela — pivot coordenador ↔ evento)

| Coluna | Tipo | Regras |
|--------|------|--------|
| id | bigint PK | |
| event_id | bigint FK→events | `cascade` |
| user_id | bigint FK→users | `cascade` |
| timestamps | | |

Único: `(event_id, user_id)`. A presença de ≥1 linha para um usuário coordenador o torna "restrito".

### 3.2 `User` — relação + helpers

- `events(): BelongsToMany` (via `event_user`).
- `isEventScoped(): bool` — `isCoordenador() && events()->exists()`.
- `allowedEventIds(): ?Collection` — **fonte única da verdade** do escopo:
  - retorna `null` quando o usuário **não** é restrito (Admin, ou Coordenador sem eventos, ou Usuário) →
    "sem restrição por evento";
  - retorna a coleção de `event_id` quando é coordenador restrito.

### 3.3 `Event` — relação inversa

- `coordinators(): BelongsToMany` (via `event_user`) — usada para filtrar a lista de usuários (§5).

## 4. Cadastro do colaborador

No formulário de usuário (`users/_form.blade.php` + `UserController`):

- Quando o perfil selecionado for **Coordenador**, exibir um **multiselect de eventos** ("Eventos que este
  coordenador pode ver") — análogo ao multiselect de quadros que já existe para o perfil Usuário.
- Deixar vazio = coordenador vê tudo (mantém o comportamento atual). Selecionar 1+ eventos = restrito.
- Persistência: `UserController` passa a sincronizar `event_user` (`$user->events()->sync(...)`) quando o
  papel é Coordenador; para os demais papéis, `detach()` (mesmo padrão do `syncBoards()` já existente).
- Quem gerencia esse vínculo: Admin sempre. Um coordenador restrito, se puder criar/editar usuários
  (§5, menu Usuários), só pode atribuir eventos que ele mesmo possui (ver §6).

## 5. Matriz de visibilidade (coordenador restrito logado)

| Tela / Menu | Comportamento para o coordenador restrito |
|---|---|
| **Painel** (menu) | Visível |
| **Quadros / Processos** (menu) | Visível |
| **Todos os Cards** (menu) | Visível |
| **Captura rápida** (menu) | Visível (ferramenta pessoal — sem mudança) |
| **Banco de Preços** (menu) | Visível (não é por evento — sem filtro) |
| **Setores** (menu) | **Oculto** + rota bloqueada |
| **Empresas** (menu) | **Oculto** + rota bloqueada |
| **Fornecedores** (menu) | **Oculto** + rota bloqueada |
| **Templates de Cards** (menu) | **Oculto** + rota bloqueada |
| **Eventos** (menu) | Visível, porém a **lista mostra só os eventos vinculados** a ele |
| **Usuários** (menu) | Visível, porém a **lista mostra só usuários que compartilham ao menos um evento** com ele |
| **Painel → Vencendo hoje / Meus quadros / Atualizados recentemente** | Só cards dos eventos vinculados |
| **Todos os Cards** (listagem) | Só cards dos eventos vinculados |
| **Kanban do quadro** | Só cards dos eventos vinculados; contador das colunas idem |
| **Kanban → filtro "Eventos"** | Só os eventos vinculados aparecem no dropdown |
| **Quadros / Processos** (listagem) | Quadros continuam visíveis; a **contagem de cards por quadro** reflete só os eventos vinculados |

> Os menus a ocultar são: **Setores, Empresas, Fornecedores e Templates de Cards**. **Banco de Preços**
> permanece visível (não é por evento, e foi citado como acessível) — atenção: o cadastro de
> **Fornecedores** é ocultado, mas o **Banco de Preços** (`prices.categorias.*`, leitura de preços por
> categoria) continua acessível; são controllers/rotas distintos. Admin e coordenador não-restrito não
> têm nenhuma dessas mudanças.

## 6. Autorização / enforcement (servidor)

Esconder o menu não basta — cada ponto abaixo precisa barrar/escopar no backend:

- **Menus ocultos → rotas bloqueadas**: `SetorPolicy`, `EmpresaPolicy`, `FornecedorPolicy` e
  `CardTemplatePolicy` passam a negar `viewAny`/`view`/`create`/`update`/`delete` para coordenador
  restrito. Como o grupo de rotas usa `role:admin,coordenador` (não distingue restrito), o bloqueio fica
  nas Policies (os controllers já chamam `$this->authorize(...)`). **Atenção ao `Gate::before`**
  (`AuthServiceProvider`) que libera Admin em tudo — o retorno `null` das policies para não-admin mantém a
  checagem normal; só é preciso garantir que a policy retorne `false` para o coordenador restrito (mesma
  lição das specs 17). **Não** bloquear o Banco de Preços (`PriceCategoriaController`/`prices.categorias.*`),
  que permanece acessível.
  - Observação sobre o cadastro rápido de fornecedor no card (`fornecedores.quick`): hoje liberado a
    qualquer autenticado. Como o menu/cadastro de Fornecedores é ocultado para o coordenador restrito,
    avaliar se o atalho "novo fornecedor" dentro do modal de card também deve sumir para esse perfil
    (coerência) — ver §7.
- **Escopo de cards — helper único**: criar um scope reutilizável `Card::scopeVisibleTo(Builder, User)`
  que aplica `whereIn('event_id', $ids)` quando `allowedEventIds()` não é `null` (e não aplica nada quando
  é `null`). Usar esse scope em **todas** as consultas de card:
  - `DashboardController::index()` — `$dueTodayCards`, `$recentCards` e a contagem `cards_count` dos
    boards (`withCount(['cards' => ...])`).
  - `CardController::index()` ("Todos os cards").
  - `BoardController::index()` — `withCount` de cards por board.
  - `BoardController::kanbanData()` — a query de `columns.cards` (hoje já filtra
    `whereNull('concluded_at')`/`archived_at`; soma-se o filtro por evento).
  - `CardController::show()` / route-model-binding de `{card}` — um coordenador restrito **não pode abrir
    um card de evento fora do seu escopo** (retorna 403/404). Reaproveitar `authorize('view', $card)` com
    a `CardPolicy::view()` também checando o escopo de evento.
- **Filtro de eventos do Kanban + select de evento na criação/edição de card**:
  `CardFormOptionsService::globalOptions()` monta `events`. Passa a devolver só os eventos permitidos
  quando o usuário é restrito (o serviço precisa do usuário logado — usar `auth()->user()`). Como o modal
  de card usa essa mesma lista (`cfg.events`) tanto no filtro do Kanban quanto no **select de Evento ao
  criar/editar um card**, o coordenador restrito automaticamente só consegue escolher entre os eventos
  dele — atendendo ao requisito de que, ao criar um card, o select de Evento mostre apenas os eventos
  permitidos. Reforçar no servidor: `StoreCardRequest`/`UpdateCardRequest` (ou a Action) devem **recusar
  um `event_id` fora do escopo** do coordenador restrito, para o select filtrado não ser burlado.
- **Lista de eventos** (`EventController::index` + `EventPolicy`): coordenador restrito vê/edita só os
  eventos vinculados; não vê os demais (query `whereIn('id', allowedEventIds())`). Criar novo evento: ver
  §8 (decisão em aberto).
- **Lista de usuários** (`UserController::index` + `UserPolicy`): coordenador restrito vê só usuários que
  compartilham ao menos um evento com ele — `whereHas('events', fn ($q) => $q->whereIn('events.id', $ids))`
  (e, opcionalmente, ele mesmo). Ao criar/editar usuário, só pode atribuir eventos do seu próprio escopo.

## 7. Frontend (sidebar + telas)

- `sidebar.blade.php`: além de `$isManager`, calcular `$isEventScoped = auth()->user()->isEventScoped()`.
  Envolver **Setores / Empresas / Fornecedores / Templates** em `@if ($isManager && ! $isEventScoped)`.
  Apenas **Eventos** permanece sob `$isManager` na seção "Cadastros" para o coordenador restrito. (Hoje
  "Templates" está dentro de um `@if ($isManager)` aninhado redundante — trocar pela nova condição.)
- Atalho "Novo fornecedor" no modal de card (`card-panel.blade.php`/`quickFornecedor`): ocultar para o
  coordenador restrito, mantendo a coerência com o cadastro de Fornecedores oculto (§6).
- As telas de listagem (painel, todos os cards, kanban, eventos, usuários) apenas consomem os dados já
  escopados pelo backend — sem lógica de permissão no Blade além de esconder menus.
- Kanban: o dropdown de eventos é populado por `cfg.events` (já escopado pelo serviço) — nenhuma mudança
  de JS necessária além de receber a lista menor.

## 8. Decisões (confirmadas com o usuário) e pontos ainda em aberto

**Confirmadas:**

- **Cards sem `event_id` ficam invisíveis** para o coordenador restrito (não mostra). Consequência: cards
  sem evento não aparecem em nenhuma tela para esse perfil.
- **Criação de card**: o select de Evento mostra **somente os eventos permitidos** do coordenador
  restrito (via `cfg.events` já escopado + validação no servidor — §6).
- **Menu Fornecedores é ocultado** (além de Setores, Empresas e Templates). Banco de Preços permanece.
- **Escopo é só para o perfil Coordenador.** O perfil Usuário continua restrito por **quadro**
  (`user_board`), como hoje; não recebe vínculo por evento nesta spec.

**Ainda em aberto (decididos pelo caminho mais consistente; confirmar na implementação):**

- **Coordenador sem evento vinculado = vê tudo** (opt-in retrocompatível). Alternativa: sem evento = não
  vê nada. Adotado "vê tudo" para não quebrar coordenadores atuais.
- **Evento obrigatório ao criar card** para o coordenador restrito: recomendado (senão ele criaria um card
  sem evento que, pela regra acima, ficaria invisível até para ele mesmo). A spec assume que o evento
  passa a ser obrigatório nesse fluxo.
- **Criação de eventos por coordenador restrito**: assumido **não permitido** (um evento novo, não
  vinculado a ele, sumiria da lista logo após criar). Ele vê/edita apenas os eventos vinculados.
- **Menu Usuários para coordenador**: hoje `UserPolicy::viewAny` já permite coordenador. Mantido, apenas
  com a lista filtrada por evento. Se a intenção for que coordenador restrito **não** gerencie usuários,
  basta negar na policy — decisão do produto.

## 9. Rotas

Nenhuma rota nova é estritamente necessária — o vínculo evento↔coordenador é salvo junto do
`users.store`/`users.update` já existentes (como o vínculo de quadros). Toda a mudança é em Policies,
escopo de consultas, `CardFormOptionsService` e visibilidade de menus.

## 10. Critérios de aceite

- [x] Cadastro de usuário com perfil Coordenador permite vincular 1+ eventos (multiselect); vazio = sem
      restrição.
- [x] Novo pivot `event_user`; `User::events()`, `isEventScoped()`, `allowedEventIds()`; `Card::scopeVisibleTo()`.
- [x] Coordenador restrito logado **não vê** os menus Setores, Empresas, Fornecedores e Templates de
      Cards, e recebe 403 ao acessar essas rotas diretamente. Banco de Preços continua acessível.
- [x] Menu Eventos lista só os eventos vinculados; menu Usuários lista só usuários que compartilham evento.
- [x] Ao criar/editar um card, o select de Evento mostra só os eventos vinculados; enviar um `event_id`
      fora do escopo é recusado no servidor (evento vira obrigatório para esse perfil).
- [x] Painel (Vencendo hoje / Meus quadros / Atualizados recentemente) mostra só cards dos eventos
      vinculados; contagem de cards por quadro idem.
- [x] "Todos os Cards" mostra só cards dos eventos vinculados.
- [x] Kanban do quadro mostra só cards dos eventos vinculados; o filtro "Eventos" só lista os eventos dele.
- [x] Coordenador restrito recebe 403 ao abrir por URL um card de evento fora do seu escopo.
- [x] Admin e coordenador **sem** eventos vinculados continuam vendo tudo, sem nenhuma mudança.
- [x] `pint --dirty` e `npm run build` limpos. (Sem testes Playwright — fora de escopo por decisão do usuário.)
