# 28 — Perfis de acesso

> **Modelo recomendado:** `opus` — troca a base de autorização do sistema inteiro (rotas, Policies, menu,
> escopos de consulta), como a [20](20-coordenador-por-evento.md). Melhoria pós-entrega.

Substitui os papéis fixos **Admin / Coordenador / Usuário** ([04](04-autenticacao-e-permissoes.md)) por
**perfis de acesso** cadastrados pelo Admin. Um perfil tem um nome, uma lista de módulos ligados ou
desligados e uma lista de quadros ligados ou desligados. Cada usuário que não é admin tem um perfil.

Definido em 26/11/2026, em sessão de perguntas e respostas com o usuário.

**Premissas:**
- **Admin vê e faz tudo.** Não tem perfil.
- **Módulo ligado = ver, criar, editar e excluir tudo daquele módulo.** Não existe "só ver".
- **Um perfil por usuário**, obrigatório para quem não é admin.
- **O acesso de ninguém muda no dia da troca**, exceto o listado em §9.2: a migração cria perfis que
  reproduzem o acesso de hoje.
- O perfil decide **quais telas** a pessoa usa. A restrição por evento (§6) decide **quais dados** ela vê
  dentro dessas telas. As duas são independentes.

**O que esta spec substitui:**
- §3 a §5 da [04](04-autenticacao-e-permissoes.md): papéis e matriz de permissão.
- O "coordenador restrito" da [20](20-coordenador-por-evento.md), que passa a valer para qualquer usuário
  que não é admin (§6).
- O checkbox de Locações da [24](24-gestao-de-locacoes.md) §11.
- O vínculo usuário ↔ quadro (`user_board`).

---

## 1. Conceitos

| Termo | O que é |
|---|---|
| Administrador | `users.is_admin = true`. Ignora o perfil. |
| Perfil de acesso | Nome + módulos ligados + quadros ligados. Tabela `access_profiles`. |
| Módulo | Área do sistema que se liga ou desliga no perfil. Lista fixa no código (enum `AccessModule`, §3). |
| Quadro no perfil | Cada quadro de departamento aparece como um item próprio no perfil (§4). |
| Eventos visíveis | Restrição opcional no cadastro do usuário: "Todos" ou "Somente os selecionados" (§6). |

**Nome no código: `AccessProfile`, não `Profile`.** `Profile` já é a tela "Meu perfil"
(`ProfileController`, rotas `profile.*`, `resources/views/profile`). Na interface o rótulo é
**"Perfil de acesso"** e, no menu, **"Perfis de acesso"**.

---

## 2. O que fica fora do perfil

**Sempre liberado para qualquer usuário autenticado:**
- Painel, Notificações e Meu perfil.
- Quadros / Processos e Todos os Cards, que mostram só os quadros e cards que o usuário acessa.
- Os formulários públicos (`/f`, `/minuta`, `/nota`) não mudam.

**Uso dentro do card.** Depende só de ter acesso ao quadro do card, nunca de módulo:
- Buscar e escolher Empresa, Fornecedor, Evento, Centro de custo e Conta bancária.
- Cadastro rápido de Empresa (`empresas.quick`) e de Fornecedor (`fornecedores.quick`) e consulta de CNPJ.
- Histórico de preço do fornecedor (`fornecedores.price-history`).
- Usar um template ao criar card (`templates.import`).
- "Enviar para o Financeiro" (`cards.finance.*`) e o autocomplete de descrição (`finance.presets.index`).
- Gerar link de minuta e de nota fiscal.

**Só do Admin. Nunca entram em perfil:**
- Usuários.
- Perfis de acesso.
- Reabrir prestação de contas fechada (`finance.reopen`). Quem tem Financeiro dos Eventos fecha, mas não
  reabre.

---

## 3. Catálogo de módulos

Enum `App\Domain\Enums\AccessModule` (string), com `label()`, `group()` (enum `AccessModuleGroup`) e
`icon()` (Font Awesome). O valor é o que se grava no banco. Renomear um valor exige migration de dados.

| Grupo | Módulo | Valor | O que libera |
|---|---|---|---|
| Quadros | Configurar quadros | `boards_configure` | Criar, editar e excluir quadro; colunas, campos, aprovadores, formulário externo, formulário do fornecedor e chave de custos fixos. **Dá acesso a todos os quadros** (§4). |
| Cadastros | Setores | `setores` | `setores.*` |
| Cadastros | Empresas | `empresas` | `empresas.*` (a tela de cadastro) |
| Cadastros | Centros de custo | `cost_centers` | `cost-centers.*` |
| Cadastros | Contas bancárias | `bank_accounts` | `bank-accounts.*` |
| Cadastros | Custos fixos | `fixed_costs` | `fixed-costs.*` **e o quadro de custos fixos** (§4) |
| Cadastros | Fornecedores | `fornecedores` | `fornecedores.*` (a tela de cadastro) |
| Cadastros | Categorias de fornecedor | `fornecedor_categorias` | `fornecedor-categorias.*` |
| Cadastros | Eventos | `events` | `eventos.*` |
| Cadastros | Templates de Cards | `templates` | `templates.*`, `template.items.*` |
| Financeiro | Financeiro dos Eventos | `finance` | `finance.*`, menos configurações e reabrir; e o Planejamento antigo (`plans.*`, `entries.*`, `financial.*`) |
| Financeiro | Banco de Preços | `prices` | `prices.*`: consultar e gerenciar registros |
| Financeiro | Config. Financeiro | `finance_settings` | `finance.settings.*`, `finance.sources.*`, `finance.presets.store/update/destroy` |
| Operação | Captura rápida | `captures` | `captures.*` |
| Operação | Locações | `rentals` | `rentals.*` inteiro, inclusive o que hoje é "gerenciar": encerrar, reabrir e reativar pedido; cadastrar, editar e excluir item; ver investimento |
| Operação | Licitações | `bids` | `bid.*` |

- `fornecedor-categorias.quick` (cadastro rápido de categoria dentro do formulário de fornecedor) libera
  com **Fornecedores ou Categorias de fornecedor**.
- **Módulo novo no sistema** = novo valor no enum e uma linha nesta tabela. Começa desligado em todos os
  perfis.

---

## 4. Quadros

- Cada quadro **ativo e que não é de custos fixos** aparece no perfil como um item ligado ou desligado
  (`access_profile_board`).
- **Quadro novo** começa desligado em todos os perfis.
- **Configurar quadros ligado** = acesso a todos os quadros, inclusive os criados depois. Na tela do
  perfil, os itens de quadro ficam ligados e travados, com a dica "Incluído em Configurar quadros".
- **Quadro de custos fixos** (`boards.fixed_costs = true`): não aparece na lista de quadros do perfil. O
  acesso vem só do módulo **Custos fixos**. Configurar quadros sozinho não abre esse quadro.
- **Quadro inativo**: os vínculos ficam gravados, mas o quadro não aparece na tela do perfil.
- **Excluir quadro** apaga os vínculos (`cascadeOnDelete`).
- **Responsável por card de um quadro fora do perfil**: continua vendo aquele card, só leitura, e
  recebendo a notificação ([22](22-notificacoes.md)). Mover ou editar o card exige o quadro.

Régua única em `User::canAccessBoard(Board)`, que continua sendo a régua de escrita e do menu:

```
admin                              → sim
quadro de custos fixos             → hasModule(fixed_costs)
hasModule(boards_configure)        → sim
senão                              → quadro está ligado no perfil
```

---

## 5. Aprovadores de etapa

Altera §2 e §4 da [17](17-fluxo-de-aprovacao-de-etapas.md):

- Aprovador = **qualquer usuário ativo com acesso ao quadro** (`canAccessBoard`), não só admin.
- O select de aprovadores na configuração da coluna lista os usuários ativos com acesso ao quadro.
- `BoardColumn::isApproverFor()` passa a exigir: estar na lista da coluna, estar ativo e ter
  `canAccessBoard()` no quadro. Quem perde o acesso deixa de ver o botão Aprovar, e o servidor recusa
  com o erro amigável da spec 17.
- A validação dos aprovadores sai do `BoardColumnController`, que hoje conta não-admins no próprio
  controller, para um Form Request.
- Os aprovadores já configurados, hoje todos admins, continuam válidos.

---

## 6. Eventos visíveis (restrição por evento)

Generaliza a [20](20-coordenador-por-evento.md) para **qualquer usuário que não é admin**.

**Cadastro do usuário:**
- Campo "Eventos visíveis" com duas opções: **Todos** (padrão) ou **Somente os selecionados**, com um
  multiselect de eventos. "Somente os selecionados" exige ao menos 1 evento.
- Usa a mesma tabela `event_user`, sem mudança de estrutura.
- `User::isEventScoped()` = não é admin e tem ao menos 1 linha em `event_user`. `allowedEventIds()` mantém
  o contrato: `null` = sem restrição.

**O que a restrição filtra** (só dados ligados a evento):
- **Cards**: Kanban, Todos os Cards, Painel, notificações e link direto. Card sem evento fica escondido,
  **exceto no quadro de custos fixos**: custo fixo é da empresa e nunca tem evento. `Card::scopeVisibleTo()`
  ganha essa exceção.
- **Eventos**: a lista e a edição mostram só os eventos do usuário.
- **Financeiro dos Eventos**: só as planilhas dos eventos do usuário.
- **Select de Evento no card**: só os eventos do usuário. Fora do quadro de custos fixos, o evento é
  obrigatório ao criar card (regra da 20 mantida).

**O que a restrição não filtra:** tudo que não é ligado a evento: Setores, Empresas, Fornecedores, Centros
de custo, Contas bancárias, Custos fixos, Templates, Banco de Preços, Locações, Licitações e Captura
rápida. Quem decide é o perfil. Saem do código os `! isEventScoped()` que hoje escondem menus e barram
Policies (Setor, Empresa, CostCenter, BankAccount, CardTemplate, FixedCost, Locações e sidebar).

**Criar evento sendo restrito:** permitido, com o módulo Eventos. O evento criado entra no `event_user` de
quem criou, na mesma transação da Action de criação. Editar e excluir continuam limitados aos eventos do
usuário.

**Admin** nunca é restrito. Marcar Administrador remove os eventos do usuário.

---

## 7. Telas

### 7.1 Perfis de acesso (Administração → Perfis de acesso, só Admin)

**Lista** (`/perfis-de-acesso`):
- Colunas:
  - **Nome**.
  - **Módulos**: contagem, ex.: "8 de 16".
  - **Quadros**: "Todos" quando Configurar quadros está ligado; senão, a contagem.
  - **Usuários**: contagem, com link para a lista de usuários filtrada pelo perfil.
  - **Ações**: Editar, Duplicar e Excluir.
- Busca por nome e paginação no servidor, no padrão das demais listagens.
- Botão "Novo perfil" no cabeçalho (`x-page-header`).

**Formulário** (criar e editar):
- **Nome**: obrigatório, até 60 caracteres, único.
- **Uma seção por grupo** (Quadros, Cadastros, Financeiro, Operação), cada uma com "Ligar todos" e
  "Desligar todos".
- **Cada módulo é uma chave liga/desliga**, com ícone Font Awesome e rótulo. Componente novo
  `x-form.toggle` (Alpine). Ligado em `brand-orange`, que o DESIGN.md reserva para estado ativo.
- **Seção Quadros**:
  - Primeiro, "Configurar quadros", com a dica "Dá acesso a todos os quadros, inclusive os criados depois".
  - Abaixo, um item por quadro ativo, com o ícone e o nome do quadro.
- **Dica fixa em Custos fixos**: "Inclui o quadro de custos fixos."
- **Rodapé**: Cancelar e Salvar (CTA laranja). Toast do SweetAlert2 ao salvar.

**Duplicar:** cria "Cópia de {nome}" com os mesmos módulos e quadros e abre a edição. Se o nome já
existir, acrescenta "(2)", "(3)"…

**Excluir:**
- Confirmação pelo SweetAlert2.
- Com usuários vinculados, a exclusão é bloqueada na Action com a mensagem: "Este perfil está vinculado a
  N usuário(s). Troque o perfil deles antes de excluir."

**Mudança vale na hora:** sem cache entre requisições. O próximo clique dos usuários do perfil já usa a
regra nova, sem novo login.

### 7.2 Cadastro de usuário

Seção "Acesso", nesta ordem:
1. **Administrador** (chave). Dica: "Vê e faz tudo no sistema. Não usa perfil de acesso."
2. **Perfil de acesso** (select). Obrigatório e visível só quando não é admin. Ao lado, o link
   "Gerenciar perfis", que abre a lista em nova aba.
3. **Eventos visíveis** (§6). Visível só quando não é admin.

Saem do formulário: o select "Perfil" (papel), o multiselect de quadros e o checkbox de Locações.

Regras, nos Form Requests `StoreUserRequest` e `UpdateUserRequest`:
- `access_profile_id`: `required_unless:is_admin,1`, `exists:access_profiles,id`.
- Admin grava `access_profile_id = null` e remove os eventos.
- Não é possível tirar o Administrador do último admin ativo, nem do próprio usuário logado.

**Lista de usuários:**
- **Coluna "Perfil de acesso"**: badge escuro "Administrador" ou o nome do perfil.
- **Filtro "Perfil de acesso"** (Administrador e cada perfil), no lugar do filtro de papel. Aceita
  `?perfil={id}`, usado pelo link da lista de perfis.

**Meu perfil** (`ProfileController::destroy`): a trava "único administrador" passa a ler `is_admin`.

### 7.3 Menu (sidebar)

- Cada item aparece só com o módulo ligado (`@can('module', AccessModule::X)`).
- Uma seção some quando nenhum item dela aparece (Cadastros, Financeiro, Locações, Licitações,
  Administração).
- Os quadros do menu vêm de `Board::visibleTo()`, reescrito com a régua do §4.
- Administração: Usuários e Perfis de acesso, só para Admin.

---

## 8. Implementação

### 8.1 Banco

Migrations com prefixo `2026_11_26_…`, nesta ordem:

1. `create_access_profiles_table`: `id`, `name` string(60) único, timestamps. Sem soft delete: só se
   exclui perfil sem usuários.
2. `create_access_profile_modules_table`: `id`, `access_profile_id` (FK, `cascadeOnDelete`), `module`
   string(40), timestamps. Único `(access_profile_id, module)`.
3. `create_access_profile_board_table`: `id`, `access_profile_id` (FK, `cascadeOnDelete`), `board_id`
   (FK, `cascadeOnDelete`), timestamps. Único `(access_profile_id, board_id)`.
4. `add_access_columns_to_users_table`: `is_admin` boolean, padrão `false`, com índice;
   `access_profile_id` FK nullable, `restrictOnDelete`.
5. `migrate_roles_to_access_profiles`: migração de dados (§9).
6. `drop_legacy_role_columns`: remove `users.role`, `users.can_access_rentals` e a tabela `user_board`.

**Rollback:**
- O `down()` da migration 6 recria as colunas e a tabela, vazias.
- O `down()` da migration 5 preenche `role` por aproximação: Administrador → `admin`; perfil com
  "Configurar quadros" (o poder que só o coordenador tinha) → `coordenador`; o resto → `usuario`.
  `user_board` e `can_access_rentals` voltam a partir dos quadros e do módulo Locações do perfil.

### 8.2 Domínio

- **Enums**: `AccessModule` e `AccessModuleGroup` (§3).
- **Model `AccessProfile`**: `modules()` (HasMany `AccessProfileModule`), `boards()` (BelongsToMany),
  `users()` (HasMany).
- **Model `User`:**
  - `accessProfile()` (BelongsTo); cast boolean em `is_admin`.
  - `isAdmin()` lê `is_admin`.
  - `hasModule(AccessModule $module): bool`: admin → `true`. Senão, consulta os módulos do perfil,
    **carregados uma vez por requisição** (memo na instância: 1 consulta para módulos e 1 para quadros).
  - `canAccessBoard(Board)` com a régua do §4.
  - `isEventScoped()` e `allowedEventIds()` valendo para qualquer não-admin (§6).
  - Saem `isCoordenador()`, `canAccessRentals()`, `canManageRentals()`, `canManageFixedCosts()` e a
    relação `boards()`. Os usos passam para `hasModule()` e `canAccessBoard()`.
- **`Board::scopeVisibleTo(User)`:**
  ```
  admin → todos
  senão → (fixed_costs = false E (boards_configure OU id ligado no perfil))
          OU (fixed_costs = true E hasModule(fixed_costs))
  ```
- **`Card::scopeVisibleTo(User)`**: restrição por evento com a exceção do quadro de custos fixos (§6).
- **`UserRole`** é removido no passo 7 do §14. `UserSeeder` e `UserFactory` passam a usar `is_admin` e
  perfis.

### 8.3 Autorização

- **`Gate::before`**: admin → `true`. Sem mudança.
- **Gate `module`**: `Gate::define('module', fn (User $u, AccessModule $m) => $u->hasModule($m))`. Usado
  no menu e nas Policies.
- **Middleware `EnsureModule`** (alias `module`): `->middleware('module:empresas')`. Aceita mais de um
  valor, e qualquer um deles libera. Admin passa sempre. Sem acesso, responde 403 com a mensagem atual.
- **Middleware `EnsureAdmin`** (alias `admin`): Usuários, Perfis de acesso e `finance.reopen`.
- **Saem** `EnsureRole`, todos os `role:` de `routes/web.php` e os gates `access-rentals` e
  `manage-rentals`.
- **Policies reescritas sobre `hasModule()`:**
  - Setor, Empresa, CostCenter, BankAccount, FixedCost, Fornecedor, FornecedorCategoria, CardTemplate,
    RentalItem, RentalOrder e BidModule: o módulo correspondente.
  - Event e FinanceSheet: o módulo + o escopo por evento. `reopen` da FinanceSheet é só do Admin.
  - CardCapture: o módulo + ser o dono da captura.
  - Board: configurar exige `boards_configure` + `canAccessBoard()`.
  - Card: mesma lógica de hoje, sobre o novo `canAccessBoard()`.
  - User e a nova `AccessProfilePolicy`: só Admin.
- **Outros pontos que mudam:**
  - `VisibleNotificationsQuery`: troca o teste de papel pelos quadros acessíveis + responsável.
  - `CardFormOptionsService`: no quadro de custos fixos, os responsáveis são quem acessa o quadro pela nova
    régua.
  - `BoardController::config`: lista de aprovadores pela nova régua (§5).
  - Captura rápida: o select de quadro usa `Board::active()->visibleTo($user)`, ainda sem quadro de custos
    fixos (regra da 27).
  - Action de criação de evento: vincula o evento a quem criou, quando essa pessoa é restrita (§6).

### 8.4 Rotas

```php
// Administração — só Admin
Route::middleware('admin')->group(function () {
    Route::resource('usuarios', UserController::class)
        ->parameters(['usuarios' => 'user'])->names('users')->except('show');
    Route::resource('perfis-de-acesso', AccessProfileController::class)
        ->parameters(['perfis-de-acesso' => 'accessProfile'])->names('access-profiles')->except('show');
    Route::post('perfis-de-acesso/{accessProfile}/duplicar', [AccessProfileController::class, 'duplicate'])
        ->name('access-profiles.duplicate');
});
```

- Os grupos `role:admin,coordenador` viram um grupo `module:` por módulo, conforme o §3.
- `finance.reopen` ganha o middleware `admin`.
- `finance.presets.index` sai do grupo restrito, porque é busca usada dentro do card.

**Form Requests:**
- `StoreAccessProfileRequest` e `UpdateAccessProfileRequest`: nome único, `modules.*` entre os valores do
  enum, `boards.*` existente em `boards` com `fixed_costs = false`.
- `UpdateColumnApproversRequest` (§5).
- `StoreUserRequest` e `UpdateUserRequest`, atualizados (§7.2).

**Actions** (`app/Actions/AccessProfiles`):
- `SaveAccessProfile`: transação que sincroniza módulos e quadros.
- `DuplicateAccessProfile`.
- `DeleteAccessProfile`: bloqueia quando há usuários vinculados.
- `MigrateRolesToAccessProfiles` (§9): chamada pela migration 5 e testável isoladamente.

---

## 9. Migração dos usuários atuais

`MigrateRolesToAccessProfiles` roda em transação e lê os dados do banco em que está rodando (a produção
pode ser diferente da base local). Inclui usuários na lixeira (soft delete), para que restaurar um usuário
funcione.

1. **`role = admin`** → `is_admin = true`, sem perfil.
2. **`role = coordenador`** → perfil **"Coordenador"**, criado só se existir ao menos um coordenador:
   - Ligados: Configurar quadros, os 9 Cadastros, Financeiro dos Eventos, Banco de Preços e Locações.
   - Desligados: Config. Financeiro, Captura rápida e Licitações, que hoje são só do Admin.
   - Coordenador **com** eventos vinculados vai para um perfil próprio, **"Coordenador restrito por
     evento"**, com só o que ele via: Configurar quadros, Eventos, Financeiro dos Eventos e Banco de Preços.
   - Os vínculos em `event_user` ficam como estão e viram "Somente os selecionados".
3. **`role = usuario`** → agrupados pela combinação "quadros em `user_board` + `can_access_rentals`":
   - Cada combinação vira um perfil com os quadros da combinação, Fornecedores, Banco de Preços (§9.1) e,
     se `can_access_rentals`, Locações.
   - Nome do perfil: os nomes dos quadros em ordem alfabética, separados por " + ". Sem quadros, o nome é
     "Usuário". Com Locações, acrescenta " + Locações". Se passar de 60 caracteres, corta. Nome repetido
     ganha "(2)", "(3)"…
4. **Idempotente:** rodar de novo não duplica perfis nem vínculos.

Resultado esperado na base local em 26/11/2026:
- 6 admins.
- Perfil "Coordenador": 2 usuários.
- Perfil "Orçamentos": 3 usuários.
- Perfil "Jurídico": 1 usuário.

### 9.1 Banco de Preços dos usuários comuns — decidido: (A) ligar

Hoje o Usuário **consulta** o Banco de Preços, mas não cria, edita nem exclui registros:
`prices.store`, `prices.update` e `prices.destroy` estão no grupo admin/coordenador. Como o módulo é
liga/desliga, a migração não consegue reproduzir "só consultar". Opções:

- **(A) Ligar** Banco de Preços nos perfis gerados. Ninguém perde a consulta, mas esses usuários passam a
  poder editar registros de preço.
- **(B) Desligar.** Ninguém ganha edição, mas esses usuários perdem a consulta.

**Decidido em 26/11/2026: (A).** Depois, o Admin desliga na tela do perfil onde não fizer sentido (ex.:
Jurídico). Perder a consulta quebraria o trabalho no dia da troca. A edição a mais é um risco pequeno,
com 4 usuários internos, e se desfaz em segundos.

### 9.2 Outras mudanças de acesso no dia da troca

- Os 2 coordenadores **perdem** o menu Usuários, que passa a ser só do Admin.
- Quem tem Fornecedores **ganha** o cadastro rápido de categoria dentro do formulário de fornecedor.
- Coordenador restrito por evento não ganha nada: vai para o perfil "Coordenador restrito por evento"
  (§9, passo 2). Hoje não há nenhum (0 vínculos em `event_user`).

---

## 10. Decisões tomadas na escrita da spec

Pontos que não foram perguntados um a um. Saem das decisões da sessão; confirmar na implementação.

- **Configurar quadros não abre o quadro de custos fixos.** A regra específica (quadro de custos fixos =
  módulo Custos fixos) prevalece sobre "Configurar quadros = todos os quadros".
- **Ninguém tira o próprio Administrador**, além da trava do último admin. Evita que o Admin se tranque
  fora da tela de Usuários por engano.
- **Perfil sem nenhum módulo é permitido.** O usuário vê só Painel, Notificações, Meu perfil e os quadros
  ligados no perfil.
- **Cadastro rápido de categoria** libera com Fornecedores ou Categorias de fornecedor, porque é usado
  dentro do formulário de fornecedor.
- **Nome `AccessProfile` no código**, para não colidir com `Profile` ("Meu perfil").

### 10.1 Decisões tomadas na implementação

- **Bloco "Acesso ao quadro"** na configuração do quadro (`boards/config.blade.php`) deixou de marcar
  usuários um a um (a rota `boards.access` saiu). Agora só lista os perfis que ligam o quadro, com o link
  "Gerenciar perfis de acesso" para o Admin — editar perfil é só do Admin.
- **Excluir perfil** com usuários só na lixeira é permitido: eles ficam sem perfil e, se forem restaurados,
  o cadastro pede um perfil novo. O bloqueio conta só usuários fora da lixeira.
- **Cadastro rápido de fornecedor no card** ("Novo fornecedor") ficou visível para quem é restrito por
  evento também — a spec 20 escondia (§2: uso dentro do card não depende de módulo nem de evento).
- **Captura rápida**: as rotas, que antes eram abertas a qualquer autenticado (o menu é que só aparecia
  para o Admin), passaram a exigir o módulo, inclusive `captura/receber` (Atalho do iPhone / PWA).
- **Banco de Preços**: gerenciar registros (`prices.store/update/destroy`) passou a depender do módulo
  Banco de Preços, e não mais do cadastro de categorias de fornecedor.
- **Cache do acesso**: módulos, quadros e eventos do usuário ficam guardados nos atributos da requisição
  atual (`User::accessCached()`), não na instância — 1 consulta por tipo por requisição, e a próxima
  requisição já vê a mudança. `User::flushAccessCache()` descarta depois de salvar acesso.
- **Testes**: o trait `Tests\Concerns\CreatesAccessUsers` (`userAs('admin'|'coordenador'|'usuario'|'sem-acesso')`
  e `giveBoards()`) traduz os pacotes do §9 para os testes antigos. Os testes que verificavam regras que
  esta spec mudou de propósito (restrito por evento sem cadastros; Locações "acessar x gerenciar") foram
  reescritos para a regra nova.

---

## 11. Fora do escopo

- Níveis por módulo (só ver, criar sem excluir).
- Mais de um perfil por usuário.
- Histórico de quem alterou cada perfil.
- Permissão por coluna do quadro ou por campo do card.
- Restrição por evento dentro do perfil. Ela continua no cadastro do usuário.
- Delegar a gestão de usuários ou de perfis a quem não é admin.

---

## 12. Critérios de aceite

- [x] Admin cria o perfil "Comercial" com o quadro Orçamentos, Empresas e Banco de Preços. Um usuário com
  esse perfil vê no menu só Painel, Quadros / Processos (com Orçamentos), Todos os Cards, Cadastros →
  Empresas e Financeiro → Banco de Preços.
- [x] O mesmo usuário recebe 403 ao abrir pela URL uma tela de módulo desligado (`/setores`,
  `/financeiro`, `/locacoes`, `/licitacoes`, `/usuarios`, `/perfis-de-acesso`).
- [x] Desligar Empresas no perfil tira o acesso no próximo clique, sem novo login.
- [x] Sem o módulo Empresas, o usuário ainda escolhe e cadastra rapidamente uma empresa dentro do card.
- [x] Com Configurar quadros, o usuário vê todos os quadros, inclusive um criado depois, mas não vê o
  quadro de custos fixos sem o módulo Custos fixos.
- [x] Quadro novo aparece desligado em todos os perfis.
- [x] Com Custos fixos ligado, o cadastro e o quadro de custos fixos aparecem. Desligado, nenhum dos dois.
- [x] Excluir perfil com usuário vinculado é bloqueado com a mensagem do §7.1. Sem usuários, exclui.
- [x] Duplicar cria "Cópia de …" com os mesmos módulos e quadros.
- [x] Nome de perfil repetido é recusado.
- [x] Usuário não admin sem perfil não é salvo. Marcar Administrador limpa o perfil e os eventos.
- [x] Não é possível tirar o Administrador do último admin nem de si mesmo.
- [x] Usuário com "Somente os selecionados" (evento A) e com Custos fixos vê no Kanban só os cards do
  evento A e todos os cards do quadro de custos fixos.
- [x] Esse usuário cria o evento B e o vê na lista logo em seguida. Ele não edita o evento C, que não
  está vinculado a ele.
- [x] Usuário não admin com acesso ao quadro pode ser escolhido como aprovador e aprova. Depois de perder
  o quadro, a aprovação é recusada.
- [x] Com Financeiro dos Eventos, o usuário fecha a prestação de contas, mas não vê nem consegue usar
  "Reabrir". O Admin reabre.
- [x] Locações ligado também libera encerrar pedido, cadastrar item e ver investimento.
- [x] Na base local, a migração gera 6 admins e os perfis Coordenador (2), Orçamentos (3) e Jurídico (1).
  Cada usuário vê no menu o mesmo que via antes, exceto o §9.2.
- [x] Não sobra no código referência a `UserRole`, `role:`, `isCoordenador()`, `user_board` ou
  `can_access_rentals`.
- [x] O acesso do usuário custa no máximo 2 consultas por requisição, sem N+1 no menu nem no Kanban.

---

## 13. Testes

`tests/Feature/AccessProfiles/` (backend apenas, sem testes de front ou e2e):

- `AccessProfileCrudTest`: validações, nome único, só Admin acessa, duplicar, exclusão bloqueada com
  usuários.
- `ModuleAccessTest`: data provider sobre `AccessModule::cases()`. Para cada módulo, uma rota
  representativa responde 403 sem o módulo e 200 com ele. Admin sempre 200.
- `BoardAccessTest`: quadro ligado no perfil, Configurar quadros, quadro novo desligado, quadro de custos
  fixos pelo módulo, responsável com leitura avulsa.
- `EventScopeTest`: restrição para não-admin, exceção do quadro de custos fixos, criar evento com vínculo
  automático.
- `ApproverTest`: aprovador que não é admin e revalidação quando ele perde o acesso.
- `UserAccessFormTest`: perfil obrigatório; Admin limpa perfil e eventos; travas do último admin e do
  próprio usuário.
- `MigrateRolesToAccessProfilesTest`: agrupamento por quadros + Locações, coordenadores, nomes repetidos,
  idempotência e usuários na lixeira.

**Testes existentes:** 31 arquivos usam `UserRole` ou papéis. A `UserFactory` ganha os estados `admin()`,
`withModules(AccessModule ...)`, `withBoards(Board ...)` e `eventScoped(Event ...)`, e esses testes
passam a usá-los.

---

## 14. Ordem de implementação

1. Migrations 1 a 4, enums, models e os novos `hasModule()` e `canAccessBoard()`.
2. Migration 5, `MigrateRolesToAccessProfiles` e o teste dela.
3. Middlewares `module` e `admin`, rotas, Gate e Policies.
4. Sidebar, `Board::visibleTo()`, `Card::visibleTo()`, notificações, opções do card, captura rápida e
   aprovadores.
5. CRUD de Perfis de acesso e o componente `x-form.toggle`.
6. Cadastro e lista de usuários; Meu perfil.
7. Migration 6; remover `UserRole` e `EnsureRole`; ajustar seeders, factory e testes existentes.
8. Atualizar as specs afetadas: 03 (tabelas), 04 (§3 a §5 apontando para esta spec), 17 (§2 e §4),
   20 (nota no topo), 24 (§11), o README e o CHECKLIST.
