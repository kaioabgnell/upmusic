# CHECKLIST de Desenvolvimento — upMusic

Acompanhamento do que foi construído. **Atualizar a cada entrega.** Marque `[x]` quando concluído.
Legenda de status por fase no fim do arquivo.

**Modelo do Claude por fase:** Fases 0–5 → `sonnet` · Fases 6–9 → `opus` · Fases 10–12 → `sonnet`.
Trocar com `/model sonnet` ou `/model opus` ao iniciar cada bloco.

---

## Fase 0 — Setup do projeto  ✓ Concluída
- [x] Instalar Laravel 10 no diretório do projeto _(Laravel 10.50.2)_
- [x] Configurar `.env` com `upmusic_local` (sem recriar o banco) _(conexão validada: 0 tabelas)_
- [x] `php artisan key:generate` _(APP_KEY definida)_
- [x] Instalar Tailwind CSS + Alpine.js + Vite _(via Breeze)_
- [x] Instalar Font Awesome (self-hosted) e SweetAlert2 _(npm, empacotados no build)_
- [x] Copiar logos de `referencia/` para `public/img/` _(logo-preta.png, logo-branca.png)_
- [x] Instalar Laravel Breeze (Blade) e validar login base _(GET /login → HTTP 200)_
- [x] Configurar timezone `America/Sao_Paulo`, locale `pt_BR` _(config/app.php + .env)_
- [x] Estrutura de pastas de arquitetura limpa (Actions/Domain/Services/...) _(app/Actions, Domain/Enums, Domain/DTOs, Services, Support, Policies)_
- [x] `Model::preventLazyLoading()` em ambiente local _(AppServiceProvider + preventAccessingMissingAttributes)_

> Helper de alertas SweetAlert2 criado em `resources/js/alerts.js` (`window.upAlerts`). Cores da marca
> adicionadas ao `tailwind.config.js` (`brand.black`, `brand.orange`, ...). Registro público do Breeze
> (`/register`) a desativar na Fase 3, conforme spec 04.

## Fase 1 — Design system e layout base  ✓ Concluída
- [x] Tailwind config com tokens da marca (preto/laranja, ver DESIGN.md) _(brand.black/ink/orange..., surface, hairline, steel; fonte Inter)_
- [x] Layouts: `x-app-layout`, `x-guest-layout`, `x-public-layout` _(shell SaaS + login + layout público)_
- [x] Sidebar + topbar (navegação, logo por contraste) _(sidebar escura c/ logo branca, drawer mobile via Alpine; topbar c/ menu do usuário)_
- [x] Componentes base: page-header, data-table, form inputs, badge, empty-state _(+ form.select, form.money, form.file; text-input com foco laranja)_
- [x] Helper JS de SweetAlert2 (notifySuccess/confirmAction/notifyError) _(feito na Fase 0 — resources/js/alerts.js)_
- [x] Login re-estilizado à marca _(GET /login HTTP 200; painel de marca + form)_
- [x] Responsividade (desktop/tablet/mobile) _(sidebar fixa ≥lg, drawer <lg; grids sm/lg; tabelas com overflow-x)_

> Componentes: `x-sidebar`, `x-nav-item` (active-state + fallback p/ rotas futuras), `x-page-header`,
> `x-data-table`, `x-badge`, `x-empty-state`, `x-form.select/money/file`. `x-cloak` no CSS.
> Usuário de teste temporário: `admin@upmusic.local` / `password` (substituir pelo seeder na Fase 3).

## Fase 2 — Banco de dados (migrations + seeders)  ✓ Concluída
- [x] Migrations: users (estendida: role/setor_id/phone/avatar/active/softDeletes), setores
- [x] Migrations: empresas, fornecedores
- [x] Migrations: boards, board_columns, board_fields, user_board
- [x] Migrations: cards, card_field_values, card_attachments, card_comments, card_movements
- [x] Migrations: card_templates, card_template_items
- [x] Migrations: financial_plans, financial_entries
- [x] Migrations: services, service_prices
- [x] Migrations: external_forms, external_submissions
- [x] Índices e constraints de FK conferidos _(21 migrations rodaram limpas)_
- [x] Enums de domínio (UserRole, FornecedorTipo, FieldType, CardOrigin, CardPriority, AttachmentKind, MovementType, ExternalSubmissionStatus)
- [x] Models Eloquent + relacionamentos + casts + scopes _(20 models; Setor/Fornecedor com $table PT-BR)_
- [x] Seeders: usuários (3 perfis), setores (4), quadros/colunas do fluxo, dados de exemplo
- [x] `php artisan migrate:fresh --seed` rodando limpo _(validado: enums, relações e regras de acesso)_

> Logins de teste (senha `password`): `admin@upmusic.local` (Admin), `coordenador@upmusic.local`
> (Coordenador), `usuario@upmusic.local` (Usuário, acesso ao quadro Orçamentos).
> Convenção: models usam `$fillable`; validação real fica nos Form Requests (Fases 3+).

## Fase 3 — Autenticação e permissões  ✓ Concluída
- [x] Login/logout/recuperação de senha com o design da marca _(login, forgot, reset re-estilizados PT-BR; registro público desativado)_
- [x] Bloqueio de usuário inativo _(LoginRequest + middleware EnsureActive; validado via HTTP)_
- [x] CRUD de usuários (perfis, setor, status) _(UserController + Store/UpdateUserRequest)_
- [x] Vínculo de quadros para perfil `usuario` (user_board) _(checkboxes no form, sync condicional)_
- [x] Enum UserRole + middleware EnsureRole _(aliases `role` e `active` no Kernel)_
- [x] Policies + Gate::before p/ admin _(UserPolicy completa; Gate::before libera Admin. Policies de Board/Card/Empresa/etc. serão criadas nas respectivas fases)_
- [x] Restrições por perfil testadas _(HTTP: admin/coord `/usuarios`=200, usuario=403; Gate: coord não edita admin, não exclui; admin exclui)_

> Home `/` redireciona para dashboard (auth) ou login. Flash de sessão vira toast SweetAlert2;
> exclusão usa `<form data-confirm>` interceptado no `alerts.js`. Proteções no destroy: não excluir
> a si mesmo nem o último admin. `register.blade.php`/`RegisteredUserController` ficam órfãos (rota removida).

## Fase 4 — Cadastros base  ✓ Concluída
- [x] CRUD Setores (com bloqueio de exclusão se houver quadros) _(validado: DELETE 302 + setor mantido)_
- [x] CRUD Empresas (CNPJ validado + máscara, endereço/ViaCEP) _(Rule Cnpj; máscara Alpine; ViaCEP no blur)_
- [x] Cadastro inline de empresa para uso no card _(endpoint `empresas.quick`, JSON 201; disponível a qualquer autenticado)_
- [x] CRUD Fornecedores (PF/PJ com máscara/validação por tipo) _(documento valida CPF/CNPJ conforme tipo; máscara dinâmica)_
- [x] Busca e filtros server-side em todas as listagens _(busca + status; fornecedor por tipo)_
- [x] Endpoint JSON de busca de empresas para selects _(`empresas.search`, validado retornando JSON)_

> Infra: `App\Support\Br` (CPF/CNPJ validação+formatação), Rules `Cnpj`/`Cpf`, `@alpinejs/mask`.
> Policies `Setor/Empresa/Fornecedor` (Coordenador gerencia; Empresa `viewAny` liberado; Admin via Gate::before).
> Validado por HTTP: coord acessa gestão (200), usuário 403 na gestão mas usa busca/quick; CNPJ inválido→422, válido→201.
> Nota: parâmetros de rota ajustados (`setores`→`setor`, `fornecedores`→`fornecedor`) para o model binding.

## Fase 5 — Quadros e departamentos  ✓ Concluída
- [x] CRUD de quadros (vínculo a setor, cor, ícone, posição) _(+ opção de colunas padrão na criação)_
- [x] Menu/lista de quadros respeitando acesso do perfil _(grade de cards; usuário só vê seus quadros)_
- [x] Configuração de colunas (CRUD + reordenação drag + is_final/is_entry) _(inline autosave + SortableJS + JSON)_
- [x] Botão "Adicionar nova coluna" ao final (estilo Pipefy)
- [x] Configuração de campos de card (board_fields, tipos, obrigatórios, opções) _(inline + reorder)_
- [x] Gestão de acesso de usuários por quadro _(form de checkboxes → sync user_board)_

> Controllers: `BoardController` (index/CRUD/config/updateAccess), `BoardColumnController` e
> `BoardFieldController` (JSON: store/update/destroy/reorder). Policy `BoardPolicy` (view = canAccessBoard).
> `boards.show` é placeholder das etapas — o **Kanban interativo é a Fase 6 (opus)**.
> Validado por HTTP: store c/ colunas padrão (3), config 200, coluna/campo via JSON (201), reorder (200),
> acesso: usuário abre Orçamentos (200), Financeiro 403, /quadros/criar 403.

## Fase 6 — Kanban e cards (núcleo)  ✓ Concluída
- [x] Tela do Kanban (topbar, colunas, contadores, botão "adicionar nova coluna")
- [x] Card compacto na coluna (título, empresa, responsável, prazo, prioridade, indicadores de anexo/comentário/valor)
- [x] Criação de card (campos fixos + configuráveis, validação de obrigatórios)
- [x] Card de detalhe estilo Pipefy (slide-over) com edição
- [x] Drag-and-drop (SortableJS) persistindo coluna/posição + card_movements _(validado)_
- [x] Vínculo de empresa (+ cadastro rápido) + filtro por empresa/responsável/prioridade/busca
- [x] Anexos (geral/NF/comprovante) com upload/download/exclusão _(NF via PNG → 201)_
- [x] Comentários no card
- [x] Histórico (timeline) no card
- [x] Transição entre departamentos (botão na coluna final → quadro destino)
- [x] Card transferido aparece no quadro de destino com histórico/anexos preservados _(validado)_
- [x] Sem N+1 no carregamento do Kanban _(preventLazyLoading ativo; Kanban 200)_

> Arquitetura limpa: Actions `CreateCard`/`UpdateCard`/`MoveCard`/`TransferCard` (+ trait `SyncsCardFields`),
> `CardController` fino, `CardPolicy` (view/create/update/delete = canAccessBoard). Front: `kanban.js`
> (Alpine + SortableJS), cards renderizados no servidor, painel via fetch JSON. Anexos no disco `local`
> (download autorizado). Corrigido no fluxo: `board_column_id` opcional na transferência.
> Validado por HTTP: criar/detalhe/update/comentar/mover/anexar/transferir + card visível no destino.

## Fase 7 — Templates de cards  ✓ Concluída
- [x] CRUD de templates + itens (reordenáveis) _(editor com itens inline, coluna padrão e valores padrão de campos; reorder SortableJS)_
- [x] Importar template para o quadro (transação, múltiplos cards) _(Action ImportTemplate; validado: 2 cards criados)_
- [x] Vínculo opcional de empresa aos cards gerados _(validado: empresa aplicada a todos)_
- [x] `origin = template` e default_fields aplicados _(validado: card com origin=template e campo "Categoria"=Limpeza)_

> Import disparável na índice de templates e na topbar do Kanban ("Importar template", modal SweetAlert2).
> Colunas/campos incompatíveis com o quadro de destino são ignorados (CreateCard filtra campos válidos).
> Import autorizado a qualquer usuário com acesso ao quadro (CardPolicy::create); CRUD restrito a Coordenador/Admin.

## Fase 8 — Formulário externo  ✓ Concluída
- [x] Config de formulário por quadro (token, coluna de análise, ativo) _(auto-criado; regenerar token)_
- [x] Botão "Compartilhar formulário" no Kanban → página de gestão com link copiável
- [x] Página pública `/f/{token}` com identidade da marca (responsiva, x-public-layout)
- [x] Campos + upload de NF com validação _(CNPJ validado, mimes pdf/jpg/png/webp, máx 10MB)_
- [x] Envio cria card (origin external_form) + anexa NF + casa empresa por CNPJ _(validado end-to-end)_
- [x] Rate limiting e validação de upload _(throttle:10,1 + honeypot anti-bot)_
- [x] Tela de sucesso ao cliente

> Action `ProcessExternalSubmission` (transação): submission → card (coluna de análise) → anexo NF → vínculo.
> Componente `PublicLayout` criado (Breeze usa componentes de classe para layouts).
> Correção de dados: documentos de exemplo do seeder passaram a ser CNPJs/CPF válidos e armazenados só em dígitos.
> Validado: envio cria card com empresa casada, NF anexada, valor BR parseado; inativo → 404; honeypot bloqueia bots.

## Fase 9 — Planejamento financeiro  ✓ Concluída
- [x] CRUD de planos financeiros _(com totais e desvio na listagem)_
- [x] Lançamentos com edição rápida (estilo planilha) _(inline JSON + totais recalculados no cliente)_
- [x] Importação de Excel/CSV (pré-visualização + validação) _(CSV nativo; detecta `;`/`,`; datas dd/mm/aaaa)_
- [x] Vínculo opcional de lançamento a card _(select; sugere valores do card ao vincular)_
- [x] Comparativo previsto x realizado (desvio, % por plano/categoria/empresa) _(FinancialReportService; SUM no banco)_
- [x] Dashboard com totais e gráfico (barras previsto vs realizado) _(cards de resumo + barras CSS por categoria)_
- [x] Filtros (empresa/período/categoria/plano) + exportação CSV

> Validado por HTTP: entry "1.000,00/1.200,50" parseado; import CSV (2 linhas); agregação previsto=6500,
> realizado=6600,50, desvio=+100,50, 101,5%; export CSV 200. Rotas restritas a Admin/Coordenador (middleware).

## Fase 10 — Banco de preços  ✓ Concluída
- [x] CRUD de serviços _(nome, categoria, unidade, descrição, ativo)_
- [x] Registro de preços (data, empresa, fornecedor opcional, origem card) _(inline JSON, estilo planilha)_
- [x] Evolução histórica por cliente (série + variação abs/%) _(PriceHistoryService; validado)_
- [x] Gráfico de evolução (SVG nativo) + comparação de último preço entre clientes
- [ ] Registro de preço a partir de card concluído _(não implementado nesta fase — opcional, ver nota)_

> Leitura liberada a qualquer autenticado (usuario 200 em /precos/servicos e /precos/evolucao);
> CRUD/registro restrito a Admin/Coordenador (usuario 403 em /precos/servicos/criar).
> Validado com série real: 10/01 R$5.000,00 → 10/03 R$5.500,00 (+10%) → 10/06 R$5.200,00 (−5,5%);
> comparação entre 2 clientes exibida corretamente (Alfa e Beta).
> Índice usa `(service_id, empresa_id, reference_date)` — já criado na Fase 2.
> Pendência menor: atalho "registrar preço a partir de card concluído" ficou fora do escopo desta
> fase (registro manual cobre o caso); pode ser adicionado depois sem migrations novas.

## Fase 11 — Refino e entrega  ✓ Concluída
- [x] Revisão de responsividade em todas as telas _(sidebar/drawer, Kanban com overflow-x, painel de card `w-full max-w-lg`, formulário externo mobile-first, todas as tabelas em `x-data-table`/`overflow-x-auto`)_
- [x] Revisão de design (sem emojis, só Font Awesome, só SweetAlert2, uso correto do laranja/logos) _(0 ocorrências de emoji/alert nativo; cores hex fora do token só em inputs `type=color` de coluna, uso legítimo; logos preta/branca aplicadas corretamente por contraste via `x-application-logo`)_
- [x] Revisão de performance (índices, eager loading, paginação, agregações no banco) _(toda FK indexada, índices compostos onde necessário; listagens com `paginate()` + filtros server-side; Kanban com eager loading completo; FinancialReportService/PriceHistoryService com SUM/groupBy no banco)_
- [x] Revisão de permissões por perfil _(rotas de escrita restritas via middleware `role:admin,coordenador` OU `$this->authorize()` explícito nos casos com regra fina — Card/User —; nenhuma rota de gravação exposta sem checagem)_
- [x] `php artisan pint` e limpeza de código _(182 arquivos, 4 ajustes de estilo; removidos 6 arquivos órfãos do scaffolding padrão do Breeze: `welcome.blade.php`, `layouts/navigation.blade.php`, `nav-link`/`responsive-nav-link`, `auth/register.blade.php` + `RegisteredUserController`)_
- [ ] Testes de backend (feature/unit) nos serviços críticos _(opcional — não implementado nesta fase, ver nota)_
- [x] `npm run build` de produção _(61 módulos, build OK)_
- [x] Documentação de deploy / README de execução _(README.md reescrito: setup, seed, deploy, arquitetura)_

> Revisão de código feita por leitura direta (grep + inspeção de controllers/policies/migrations/views),
> já que o dispatch de subagentes bateu no limite de sessão. Validado por HTTP após a limpeza: todas as
> páginas principais (`/dashboard`, `/quadros`, `/usuarios`, `/precos/servicos`, `/precos/evolucao`,
> `/financeiro/planos`, `/templates`) retornando 200 com o build de produção.
> Pendência: testes automatizados de backend ficaram fora do escopo desta fase (marcados como opcional
> na spec); podem ser adicionados depois sem impacto em migrations ou rotas.

## Fase 12 — Futuro (não-MVP)  ⊘ Fora do escopo do MVP
- [ ] Integração WhatsApp ([13](13-integracao-whatsapp.md)) — _descopado_
- [ ] Notificações ao responsável em transições — _descopado_
- [ ] Relatórios/mapa adicionais do quadro — _descopado_

> Decisão do cliente: o MVP não depende de nenhum item desta fase. Nada foi implementado
> de propósito — os itens ficam documentados aqui apenas como backlog futuro, a serem
> retomados sob demanda em um novo ciclo (sem pré-requisito de migration ou refactor,
> já que a base de cards/movimentações/usuários já suporta os gatilhos necessários).

## Melhorias pós-entrega (fora da numeração de fases)
- [x] Formulário de empresa: autofill de endereço via ViaCEP ao digitar o CEP (antes só no blur)
- [x] Empresas: suporte a Pessoa Física (CPF) além de Pessoa Jurídica (CNPJ), com máscara dinâmica por tipo
- [x] Máscara de dinheiro (R$) real nos campos Valor previsto/realizado do card (`@alpinejs/mask` + `$money`)
- [x] Kanban: destaque visual (borda) + tooltip nos cards com prazo para hoje/amanhã
- [x] **Conclusão de card**: na etapa Final de um quadro, além de "enviar para outro departamento", é possível
      **concluir** o card (com confirmação) — ele deixa de aparecer em qualquer quadro, preservando histórico
- [x] **Todos os Cards** (novo item de menu): listagem global de cards de todos os quadros e status, com
      filtros por empresa/quadro/coluna/status e busca por título; abrir um card mostra todo o conteúdo
      (campos, anexos, comentários, histórico) e permite **reabrir e enviar para um quadro**

> Card concluído: `concluded_at`/`concluded_by` em `cards` (migration `2026_07_16_000001`), Actions
> `ConcludeCard`/`ReopenCard`, novos tipos de movimento `conclusion`/`reopening`. Reabrir exige escolher
> um quadro de destino (reaproveita a lógica de `TransferCard`). Corrigido de quebra: `transfer()`/`reopen()`
> agora verificam acesso também ao quadro de **destino** (`canAccessBoard`), não só ao de origem.
> Validado por HTTP: concluir esconde do Kanban e do contador do quadro; reabrir reenvia e reaparece no
> quadro de destino com movimento registrado; listagem `/cards` escopada por perfil (Usuário só vê seus
> quadros) e bloqueia 403 em concluir/ver card fora do seu acesso; filtros por status/quadro/coluna testados.
- [x] Card-panel: abas **Detalhes / Comentários / Histórico** (antes tudo empilhado numa rolagem só)
- [x] **Cadastro de Eventos** (nome, local, responsável, telefone, e-mail, data de início/fim) — novo módulo
      completo (CRUD Admin/Coordenador) vinculável a cards como "para qual evento aquele orçamento vai";
      nome do evento aparece no card do Kanban e na listagem `/cards`
- [x] Tradução PT-BR das mensagens de validação padrão do Laravel (`lang/pt_BR/validation.php` + `auth.php` +
      `passwords.php` + `pagination.php`) — o app já estava com `APP_LOCALE=pt_BR`, mas nunca existiu o
      arquivo de idioma, então toda mensagem de validação sem Rule customizada caía em inglês (ex.: "The
      email must be a valid email address."); corrigido em todo o sistema, não só nas telas novas

> Eventos: tabela `events` + `cards.event_id` (nullable, `nullOnDelete`) — migrations `2026_07_17_000001/2`.
> Segue exatamente o padrão de Setores (Policy/Form Requests/Controller/rotas `role:admin,coordenador`,
> `_form` compartilhado create/edit). Select de evento no card fica aberto a qualquer usuário com acesso
> ao quadro (mesmo tratamento de `$empresas`), só a gestão do cadastro é restrita.
> Validado por HTTP: CRUD completo; `end_date` anterior a `start_date` rejeitado (422, mensagem em PT-BR);
> card criado com evento aparece no Kanban (ícone de calendário) e na coluna "Evento" de `/cards`; Usuário
> recebe 403 em `/eventos` e não vê o item no menu; exclusão de evento com card vinculado é bloqueada.
- [x] Redesenho da barra de filtros do quadro (popover "Filtros" em vez de selects soltos que quebravam em
      telas menores) + novo filtro por **Evento**
- [x] Card-panel: modal centralizado (estilo Pipefy) com ações rápidas de **Responsável** (busca com
      foto/iniciais), **Vencimento** (date picker em popover) e **Prioridade**, além de coluna lateral fixa
      **"Mover card para fase"** (mostra colunas anteriores/seguintes do quadro com a cor real de cada uma)
- [x] Formulário externo (`/f/{token}`): novo campo obrigatório **Dados para pagamento**; confirmado que
      todos os demais campos já eram obrigatórios (client e servidor)

> Formulário externo: `payment_data` (text, nullable) em `external_submissions` — migration
> `2026_07_16_000003`. Validado inline em `PublicFormController::submit` (sem Form Request nessa tela) e
> persistido por `ProcessExternalSubmission`. Exibido também em "Envios recentes"
> (`external/manage.blade.php`), truncado com tooltip do texto completo.
> Validado por HTTP: submissão sem o campo é rejeitada (422 → mensagem "O campo dados para pagamento é
> obrigatório."); submissão completa persiste o texto e gera o card normalmente; dado aparece na tela de
> gestão do formulário para o Admin/Coordenador.
- [x] Formulário indisponível (link inválido/desativado): página com identidade visual do sistema (logo,
      mensagem clara) no lugar do 404 genérico do Laravel
- [x] Configuração do formulário externo: seção **Configuração** movida para o topo (antes do **Link
      público**) e novo campo **Evento** — o card criado a partir de um envio já nasce vinculado a esse
      evento; o título exibido ao cliente vira automaticamente "Envie os dados para o evento {nome}"
      quando um evento está selecionado

> `external_forms.event_id` (nullable, `nullOnDelete`) — migration `2026_07_16_000004`. `ProcessExternalSubmission`
> propaga `$form->event_id` para o card criado (reaproveita o suporte a `event_id` já existente em `CreateCard`).
> Validado por HTTP: token inválido/formulário inativo mostra a página nova; configurar um evento no formulário
> e visitar o link público mostra o título dinâmico corretamente; submissão completa gera card com `event_id`
> igual ao configurado no formulário.
- [x] Listagem de usuários (`/usuarios`): avatar (foto quando houver, iniciais caso contrário) ao lado do nome,
      reaproveitando o componente `<x-user-avatar>`
- [x] **Kanban reativo + carregamento assíncrono** (ver [Spec 14](14-kanban-reatividade-assincrona.md)):
      quadro (`/quadros/{board}`) não recarrega mais a página em nenhuma mutação — criar, editar, mover
      (drag-and-drop e "mover para fase"), excluir, concluir e transferir atualizam `this.columns` (estado
      reativo único) na hora e fecham o modal, com toast de sucesso. Cards deixaram de ser embutidos no HTML:
      chegam por uma segunda requisição (`GET quadros/{board}/kanban`), com skeleton por coluna enquanto
      carregam. Filtros e busca (com debounce) reaplicam via refetch, sem submit/reload, atualizando a URL via
      `history.replaceState`. Visão Lista também passa a renderizar no cliente (`x-for`), a partir do mesmo
      estado.

> Novo `app/Support/CardPresenter.php` (`compact()`) — único lugar que define o shape compacto do card,
> usado tanto por `BoardController::kanbanData()` quanto pelas respostas de `CardController::store()/update()`
> (antes devolviam o JSON completo de detalhe, ignorado pelo front que só dava reload). SortableJS × Alpine
> `x-for`: resolvido revertendo a mutação de DOM que o Sortable faz em `onEnd` antes de mutar `this.columns`,
> deixando o Alpine como único dono do DOM (evita nó duplicado/fantasma).
> Validado por HTTP: shell da página não inclui mais cards embutidos (só metadados de coluna); endpoint novo
> devolve os cards com o mesmo shape que `store()`/`update()` passaram a retornar; criar, editar, mover,
> excluir, concluir e transferir testados diretamente contra os endpoints, cada um refletindo corretamente
> no card (posição/coluna/ausência após concluir-transferir); filtros de busca e prioridade via querystring;
> autorização do endpoint novo idêntica à de `show()` (403 confirmado para usuário sem acesso ao quadro);
> contagem de queries do endpoint (7, plana) confirma que não há N+1; nenhum `window.location.reload()`
> remanescente no fluxo do quadro; `pint`/`npm run build` limpos.
- [x] **Bugfix:** card criado pelo formulário externo com evento vinculado abria no card-panel com o select
      de Evento em branco, mesmo o card tendo `event_id` correto salvo

> Causa raiz: o corpo do modal (incluindo os `<select>` de Empresa/Evento) ficava dentro de
> `<template x-if="!loading">`, que destrói e recria o DOM a cada `openCard()`. O Alpine aplicava
> `x-model="form.event_id"` no `<select>` recém-criado **antes** de o `x-for` interno ter criado as
> `<option>`s — o navegador não encontra a option ainda inexistente e a seleção fica em branco, mesmo com
> `form.event_id` correto no estado. Fix: trocado para `x-show="!loading"` — select e options ficam
> montados desde o carregamento da página (nunca são destruídos), então a seleção sempre encontra a option
> já existente. Mesma causa raiz também afetava (silenciosamente, sem reclamação) o select de Empresa.
> Validado com reprodução real usando a própria biblioteca Alpine.js + jsdom (não apenas análise estática):
> confirmado que o padrão antigo (`x-if`) deixa `select.value` vazio mesmo com o dado certo no estado, e que
> o padrão novo (`x-show`) seleciona corretamente. Também validado por HTTP que a página continua
> renderizando normalmente após a mudança.
- [x] Modal "Nova empresa" (`quickEmpresa()` no Kanban): título fora do padrão do sistema, campos Nome/CNPJ
      com o estilo padrão (grande) do SweetAlert2, e placeholder do nome não mudava para "Nome completo" ao
      selecionar Pessoa Física

> Título: SweetAlert2 injeta seu próprio CSS via JS (depois do bundle da aplicação), então uma classe extra
> via `customClass.title` não venceria por ordem — resolvido com especificidade maior
> (`.swal2-title.up-modal-title` em `resources/css/app.css`) replicando `text-lg font-semibold text-brand-ink`,
> o padrão de título usado no resto do sistema. Campos: trocados de `class="swal2-input"` para a mesma classe
> já usada no select de Tipo e nos modais "Importar template" (`h-9 text-sm border-gray-300
> focus:border-brand-orange focus:ring-brand-orange rounded-md`). Placeholder do nome agora alterna entre
> "Razão social"/"Nome completo" junto com o de CNPJ/CPF ao trocar o Tipo.
> Validado com reprodução real (SweetAlert2 + jsdom, não só leitura do CSS compilado): confirmado que o
> título recebe a classe `up-modal-title`, que os campos não usam mais `swal2-input`, e que os dois
> placeholders (nome e documento) mudam corretamente ao selecionar Pessoa Física.
- [x] Card-panel: campo **Fornecedor** — select pesquisável (Alpine, mesmo padrão do "Responsável") com
      opção **"+ nova"** para cadastro inline (Tipo PF/PJ, Nome/Razão social, CPF/CNPJ com máscara),
      alerta de duplicidade quando o documento já está cadastrado, e seleção automática do fornecedor
      recém-criado

> `cards.fornecedor_id` (nullable, FK `fornecedores`, `nullOnDelete`) — migration `2026_07_17_000003`.
> Endpoint novo `POST fornecedores/quick` (`FornecedorController::quick`), espelhando
> `EmpresaController::quick()`. Decisão de produto: o usuário pediu explicitamente "select2", mas o projeto
> não tem jQuery (dependência do select2 real) — perguntado e confirmado usar um dropdown pesquisável em
> Alpine (mesmo padrão já usado no campo Responsável), sem adicionar jQuery/select2 como dependência nova.
> **Bug encontrado e corrigido (também no `EmpresaController::quick()`, mesma causa raiz)**: a checagem de
> duplicidade (`Rule::unique`) comparava o documento **mascarado** (ex. "123.456.789-00", como chega do
> input com máscara) contra a coluna que armazena só dígitos — nunca batia, então a duplicidade nunca era
> detectada nesse endpoint "quick" (o formulário completo de cadastro não tinha esse problema, pois usa
> `FormRequest::prepareForValidation()`, que já limpa o documento antes de validar). Corrigido fazendo
> `$request->merge()` com o documento em dígitos **antes** de validar, nos dois controllers.
> Validado por HTTP: CNPJ mascarado duplicado rejeitado com a mensagem "O fornecedor informado já está
> cadastrado no sistema."; CPF novo cadastrado com sucesso e salvo no banco só com dígitos; fornecedor
> recém-criado aparece imediatamente disponível numa nova carga da página; card criado/editado com
> `fornecedor_id` reflete corretamente ao reabrir (`GET /cards/{id}` retorna `fornecedor_id`/`fornecedor`);
> mesmo fix de duplicidade confirmado também para Empresa (regressão coberta).
- [x] Card-panel: modal sem scroll interno, escondendo o botão "Salvar" no rodapé

> Causa raiz: a correção anterior do bug de seleção (`x-if` → `x-show`, ver entrada acima) trocou
> `<template x-if="!loading"><div class="flex-1 min-h-0 flex flex-col">` por um `<div x-show="!loading">`
> **sem** essas classes de flex, deixando um wrapper extra "solto" dentro da coluna principal do modal (que é
> `flex flex-col`). Sem `flex-1 min-h-0`, esse wrapper passou a crescer conforme o conteúdo em vez de se
> limitar ao espaço disponível — estourando o `max-h-[90vh]`/`overflow-hidden` do modal e empurrando/cortando
> o rodapé (botão Salvar) para fora da área visível, sem o scroll interno do corpo (`overflow-y-auto`)
> assumir o excesso. Fix: as classes `flex-1 min-h-0 flex flex-col` voltaram para o próprio `<div
> x-show="!loading">`, removendo a div extra que ficou redundante.
> Validado: confirmado por HTTP que as classes corretas estão no elemento certo no HTML renderizado.
> **Não foi possível validar visualmente em navegador real neste ambiente** (sem ferramenta de browser
> disponível) — a correção é uma mudança pura de CSS/estrutura, sem lógica nova; recomendo confirmar
> visualmente no navegador que o botão Salvar volta a aparecer e o corpo do modal rola internamente.
- [x] Card do Kanban: borda de prazo vencendo hoje/amanhã não aparecia (nem vermelha nem laranja); adicionado
      também um anel pulsando na borda (não o card inteiro) para "vence hoje"

> Causa raiz real: `tailwind.config.js`'s `content` nunca incluía `resources/js/**/*.js` — as classes de
> `dueBorderClass()` em `kanban.js` (ex. `border-red-500`) só existem dentro de um arquivo `.js`, então o
> Tailwind nunca as via para gerar o CSS. `border-red-500` simplesmente não existia no bundle compilado
> (confirmado). `border-brand-orange` (usado no caso "amanhã") só compilava por coincidência — porque a
> mesma string aparece, sem relação nenhuma, dentro de outra classe Alpine em `show.blade.php` — uma
> dependência frágil que poderia sumir a qualquer refactor daquele trecho. Fix: adicionado
> `'./resources/js/**/*.js'` ao `content` do Tailwind, cobrindo qualquer classe referenciada a partir de JS,
> não só a desse bug específico.
> Efeito novo: `due-today-pulse` (keyframes em `resources/css/app.css`) anima um `box-shadow` expandindo e
> sumindo a partir da borda (efeito de "pulso"), sem usar `animate-pulse` do Tailwind (que desbotaria o card
> inteiro em opacidade, não só a borda).
> Validado com reprodução real (Alpine.js real via jsdom, não só leitura do CSS compilado): confirmado que
> um card com `due_status: 'today'` recebe exatamente `border-2 border-red-500 due-today-pulse`, um com
> `'tomorrow'` recebe `border-2 border-brand-orange`, e sem status recebe o padrão `border border-hairline`.
> Confirmado também que `border-red-500`, `border-brand-orange` e `.due-today-pulse` (com seu `@keyframes`)
> agora compilam de forma confiável no bundle.
- [x] **Cadastro de Categorias de Fornecedor** — novo módulo completo (CRUD Admin/Coordenador, mirror exato de
      Setor: só `nome` + `active`, soft delete, bloqueia exclusão se houver fornecedor vinculado). Botão
      "Categorias" na tela de Fornecedores. No formulário de Fornecedor, o campo Categoria (antes texto livre)
      virou um select pesquisável em Alpine (mesmo padrão do campo Fornecedor no card-panel) com opção
      **"Nova categoria"** inline (SweetAlert2, cadastra e já deixa selecionada). Categorias iniciais
      cadastradas via migration: Limpeza, Segurança, Som, Cenografia, Divulgação, Estrutura Geral, Estrutura
      Lounge, Logística, Projeto.

> `fornecedor_categorias` (migration `2026_07_18_000001`, com o seed das 9 categorias) + `fornecedores.
> fornecedor_categoria_id` (migration `2026_07_18_000002`, FK nullable, `nullOnDelete`) substituindo a coluna
> livre `category` — a migration faz *backfill* automático: casa o texto antigo de `category` com o `nome`
> das categorias novas (case-insensitive) antes de descartar a coluna, para não perder o que já estava
> cadastrado. Novo endpoint `POST fornecedor-categorias/quick` (mesmo padrão de `fornecedores.quick`).
> **Bug encontrado e corrigido**: a relação `Fornecedor::categoria()` (`belongsTo(FornecedorCategoria::class)`
> sem indicar a FK) assumia por convenção do Eloquent a coluna `categoria_id` (baseada no nome do método),
> não a coluna real `fornecedor_categoria_id` — carregar `->categoria` lançava
> `MissingAttributeException`/`LazyLoadingViolationException`. Corrigido passando a FK explicitamente:
> `belongsTo(FornecedorCategoria::class, 'fornecedor_categoria_id')`.
> Validado por HTTP: as 9 categorias e o backfill (3 fornecedores existentes com `category` antigo — Limpeza,
> Segurança, Som — casaram certinho com as categorias novas) confirmados via tinker após migrar; botão
> "Categorias" e coluna "Categoria" (agora via relação) na listagem de fornecedores; formulário completo
> testado ponta a ponta (criar fornecedor com categoria → editar e confirmar pré-selecionado); cadastro rápido
> de categoria pelo select (com duplicidade de nome rejeitada) já disponível e selecionado na hora; CRUD de
> categoria (editar, e exclusão bloqueada quando há fornecedor vinculado) testado diretamente.
- [x] **Banco de Preços por Categoria** (ver [Spec 15](15-banco-de-precos-por-categoria.md)): histórico de
      preços pivotado de Serviço+Cliente para **Categoria de fornecedor**. Cadastro de Serviços aposentado;
      "Banco de Preços" lista categorias; evolução por categoria (sem Serviço/Cliente) somando todos os
      eventos; unidade (diária/unidade/hora/serviço completo) como enum hard-coded no cadastro da categoria;
      hook automático: card com fornecedor + valor realizado grava/atualiza um registro de preço na categoria.

> Novo enum `UnidadeMedida` (hard-coded), coluna `fornecedor_categorias.unidade`, tabela `price_records`
> (ancorada em `fornecedor_categoria_id`, com `fornecedor_id`/`card_id`(único)/`event_id`) substituindo
> `service_prices`. `PriceHistoryService` reancorado (`historyForCategoria`/`lastPriceByFornecedor`).
> Novos `PriceCategoriaController` (lista/detalhe) + `PriceRecordController` (registros); `PriceHistoryController`
> e `precos/evolucao.blade.php` pivotados (filtro só de categoria). Hook `SyncCardPriceRecord` chamado por
> `CreateCard`/`UpdateCard` (idempotente por `card_id`; remove o registro se o card perde fornecedor/valor).
> **Aposentado** o módulo de Serviços: removidos `Service`/`ServicePrice` (models), `ServiceController`/
> `ServicePriceController`, `Store/UpdateServiceRequest`, `ServicePolicy` (desregistrada no
> `AuthServiceProvider`), views `precos/servicos/*`, relações `servicePrices()` em Empresa/Fornecedor, e as
> tabelas `services`/`service_prices` (migration de drop com `down()` recriando por segurança). Migrations
> `2026_07_18_000003..000005`. Migração de dados: sem mapeamento confiável Serviço→Categoria, `service_prices`
> foi descartada (base pré-lançamento) — decisão registrada na spec §5.3/§16.
> Validado por HTTP: `/precos/categorias` lista categorias (com Unidade e nº de registros); detalhe mostra
> "Registros de preço" sem coluna Cliente e com Evento; unidade salva pelo cadastro da categoria; card criado
> via HTTP com fornecedor + valor realizado gerou o registro de preço na categoria (R$ 4.200), que apareceu na
> evolução (`?categoria_id=1`) com fornecedor/origem e no painel "Último preço por fornecedor"; update do card
> reaproveitou o mesmo registro (idempotente) e limpar o valor removeu-o; registro manual via
> `/precos/registros` OK; rota antiga `/precos/servicos` retorna 404. `pint` (211 arquivos) e `npm run build`
> limpos.
- [x] Evolução de preços (`/precos/evolucao`): select de **Fornecedor** (default: primeiro em ordem
      alfabética) para restringir a série do gráfico principal a um fornecedor específico da categoria.
- [x] Evolução de preços: nova seção **"Comparar fornecedores"** — seleciona 2+ fornecedores da mesma
      categoria e compara os preços num gráfico de linhas (uma cor fixa por fornecedor, legenda, tooltip
      por ponto e tabela com data/fornecedor/preço), com seletor de **período** (3 meses / 6 meses / 1 ano
      — default / todo o período).

> `PriceHistoryController::index()` ganhou `fornecedor_id` (default: primeiro alfabético da categoria,
> reancorado se a categoria mudar) e `compare_ids[]`/`period` para a comparação. Novo
> `PriceHistoryService::compareFornecedores()` retorna a série de cada fornecedor dentro do período.
> Paleta categórica de 8 cores (skill `dataviz`) validada via `validate_palette.js` (todos os checks OK;
> o WARN de contraste em 3 tons exige rótulo visível/tabela — já coberto pela legenda em texto + tabela
> completa). Cor por fornecedor é fixa pela posição alfabética dentro da categoria (não pela ordem de
> seleção), então o mesmo fornecedor mantém a mesma cor em qualquer combinação. Gráfico é SVG estático
> (mesmo padrão do gráfico principal já existente) com tooltip nativo (`<title>`) por ponto; todos os
> valores também ficam na tabela abaixo, sem depender de hover.
> Validado por HTTP: sem seleção mostra "selecione 2 ou mais"; com 2 fornecedores mostra as 2 polylines,
> legenda com nomes corretos e cores estáveis independente da ordem de seleção na URL; período "1 ano"
> (default) exclui corretamente um registro de teste com 2 anos de idade, que só aparece com "todo o
> período"; fornecedor de outra categoria enviado no filtro é ignorado sem erro; `npm run build` era
> necessário (classes novas não estavam no bundle) — rebuildado e reconfirmado.
- [x] Evolução de preços: correção de bug (gráficos SVG feitos à mão não alinhavam datas/linhas de forma
      confiável) — ambos os gráficos (evolução de um fornecedor e comparação entre fornecedores) migrados
      para **Chart.js** (`type: 'time'` com `chartjs-adapter-date-fns`), eixo X por data real, eixo Y por
      valor (BRL), tooltip com dado por ponto. Layout: "Comparar fornecedores" saiu de seção sempre visível
      e virou aba **"Comparação"**, junto de uma aba **"Evolução"**, alternadas por um toggle abaixo do
      título (mesmo padrão visual do toggle Kanban/Lista de `boards/show.blade.php`).

> `chart.js` + `chartjs-adapter-date-fns` (+ `date-fns`) instalados via npm e registrados globalmente em
> `resources/js/app.js` (`window.Chart`, módulos `LineController/LineElement/PointElement/LinearScale/
> TimeScale/Legend/Tooltip/Filler`). `precos/evolucao.blade.php`: `<canvas>` substitui os `<polyline>` SVG;
> dados repassados como `{x: data ISO, y: preço}` direto do `@php` da view (controller/service inalterados).
> Gráfico de comparação usa `interaction: { mode: 'index', intersect: false }` (um tooltip mostrando todas
> as séries no ponto) e legenda nativa do Chart.js (paleta e cor fixa por fornecedor mantidas). Abas
> controladas por Alpine (`x-data="{ view: 'evolucao' }"`); troca de aba dispara `.resize()` nos dois charts
> (canvas dentro de `x-show` renderiza com largura 0 se não for redimensionado ao aparecer).
> Validado por HTTP: categoria com 2 fornecedores e datas não-uniformes (01/06/08/10/16 jul) — JSON embutido
> no `<script>` confirma pontos `{x,y}` corretos e cor por fornecedor estável pela ordem alfabética; select
> de fornecedor continua default no primeiro alfabético; categoria sem fornecedores e categoria inexistente
> (404) preservam os empty states; classes Tailwind novas (`gap-1.5`, `h-8`, `.rounded`, `px-3`, `shrink-0`)
> conferidas no bundle após `npm run build` (JS subiu para ~444 kB com Chart.js). `pint --dirty` limpo.
- [x] Correção de 2 bugs no Chart.js recém-introduzido: (1) `window.Chart is not a constructor` — `app.js`
      carrega como `<script type="module">` (sempre adiado), então o `<script>` inline da view rodava antes
      de `window.Chart` existir; envolvido em `document.addEventListener('DOMContentLoaded', ...)`. (2)
      gráfico de "Comparar fornecedores" mostrava datas vazias (ex.: um ano inteiro) quando o período
      selecionado era maior que o intervalo real dos registros — o eixo X usava os limites do **filtro**
      de período (`periodStart`/`periodEnd`) em vez do intervalo real dos dados retornados.

> Fix 2: `$effectiveStart`/`$periodEnd` (limites do filtro) trocados por `$compareChartStart`/
> `$compareChartEnd`, calculados a partir do min/max real de `$compareAllPoints` (já filtrado pelo período).
> O filtro de período continua decidindo quais registros entram na comparação; o eixo do gráfico passou a
> refletir só o intervalo que tem registro.
> Validado por HTTP: `?period=1y` (default) com registros só entre 01/07 e 16/07/2026 — eixo do gráfico
> embutido no `<script>` confirmado como `min: '2026-07-01'` / `max: '2026-07-16'` (antes: `min` caía em
> 17/07/2025, um ano atrás). `npm run build` e `pint --dirty` limpos.
- [x] Correção de mais 2 bugs no "Comparar fornecedores": (1) clicar em **"Comparar"** fazia um submit GET
      que recarregava a página e voltava para a aba **Evolução**, perdendo a aba Comparação. (2) o eixo X do
      gráfico mostrava **horários** (ex. "12:00") em vez de só datas.

> Fix 1: o estado `view` do Alpine (antes local ao `<div>` das abas) subiu para um `x-data` que envolve
> também o formulário de filtro do topo; inicializado a partir de `request('view', 'evolucao')`. O form de
> filtro (categoria/fornecedor) ganhou `<input type="hidden" name="view" :value="view">` (reflete a aba
> atual em qualquer submit); o form de "Comparar" ganhou `<input type="hidden" name="view" value="comparacao">`
> fixo, já que esse submit sempre deve pousar na aba Comparação.
> Fix 2: `time.unit` das duas escalas X forçado para `'day'` — sem isso, o auto-detecção de granularidade do
> Chart.js podia escolher uma unidade menor (hora/minuto), e como `displayFormats` só cobria dia/semana/mês/
> trimestre/ano, a unidade não coberta caía no formato padrão do adaptador (que inclui hora).
> Regressão pega no processo: `$compareChartStart`/`$compareChartEnd` (novos, do fix anterior) podem ser
> `null` quando não há dados de comparação (ex.: página padrão com só 1 fornecedor selecionado), mas o
> `<script>` chamava `->format()` incondicionalmente — 500 (`Call to a member function format() on null`).
> Corrigido com `?->format(...)`; inofensivo porque nesse cenário o `<canvas id="comparar-chart">` nem existe
> no DOM, então o `if (compararCanvas)` do JS nunca chega a usar esses valores.
> Validado por HTTP: submit do form "Comparar" com `view=comparacao` na URL — `x-data="{ view: 'comparacao' }"`
> confirmado no HTML; troca de fornecedor no filtro do topo preserva a aba via `:value="view"`; ambas as
> escalas X confirmadas com `time: { unit: 'day', ... }`; página padrão (`?categoria_id=1`, sem comparação) e
> categoria sem fornecedores voltaram a responder 200 (antes do fix do null-safe, ambas davam 500). `npm run
> build` e `pint --dirty` limpos.
- [x] Card panel (`boards/partials/card-panel.blade.php`): ao selecionar um **Fornecedor**, um ícone ao lado
      do rótulo "Valor previsto" mostra em tooltip os **últimos 5 preços** daquele fornecedor (data +
      valor), a **média** e a **tendência** (evolução/alta ou redução/baixa, comparando o mais recente com
      o mais antigo da janela de 5). Atualiza ao trocar de fornecedor.

> Novo `PriceHistoryService::lastForFornecedor(Fornecedor $fornecedor, int $limit = 5)`: busca direto em
> `$fornecedor->priceRecords()` (não depende de categoria selecionada em tela nenhuma), retorna
> `records`/`average`/`trend` (`alta`/`baixa`/`estavel`, ou tudo `null`/vazio sem histórico). Novo endpoint
> JSON `GET fornecedores/{fornecedor}/preco-historico` (`FornecedorController::priceHistory`, mesma
> convenção de `fornecedores/quick` — sem prefixo `/api`, disponível a qualquer autenticado). `kanban.js`:
> `fornecedorHistoryCache` (por `fornecedor_id`, evita refetch ao reabrir o mesmo fornecedor),
> `loadFornecedorHistory()` chamado ao selecionar fornecedor no dropdown, ao abrir um card existente
> (`openCard`) e após cadastro rápido (`quickFornecedor`); cache invalidado após `save()` (o salvamento pode
> criar/atualizar um registro de preço via hook `SyncCardPriceRecord`, o que tornaria o histórico
> cacheado desatualizado). Tooltip é um popover próprio (hover), não reaproveita o tooltip genérico de texto
> já existente no quadro (que é single-line/plain-text).
> Validado por HTTP: fornecedor com 4 registros (700/650/500/600, datas 01–16/07) → `average: 612.5`,
> `trend: "baixa"` (600 < 700); fornecedor com 2 registros (400→800) → `trend: "alta"`; fornecedor sem
> registros → `records: []`, `average`/`trend` `null`; fornecedor inexistente → 404 (route model binding).
> Markup confirmado na página do quadro (`cfg.urls.fornecedorPriceHistory`, `loadFornecedorHistory` no
> clique do fornecedor, blocos do tooltip); classe Tailwind nova (`top-full`) conferida no bundle. `npm run
> build` e `pint --dirty` limpos.
- [x] Correção de 2 bugs no tooltip de histórico de preços do fornecedor (recém-criado): (1) a URL montada
      em `loadFornecedorHistory()` esquecia o sufixo `/preco-historico` — chamava `GET fornecedores/{id}`
      (rota inexistente para esse verbo, só PUT/DELETE) e caía sempre em 405, então o `catch` preenchia o
      cache com histórico vazio e o tooltip mostrava "Sem histórico" mesmo para fornecedor com registros.
      (2) o popover usava `position: absolute` dentro do corpo do painel (`overflow-y-auto`), então era
      cortado/forçava scroll em vez de aparecer por cima de tudo.

> Fix 1: `` `${cfg.urls.fornecedorPriceHistory}/${fornecedorId}` `` → `` `.../${fornecedorId}/preco-historico` ``.
> Fix 2: popover trocado de `absolute` (relativo ao ícone, preso ao overflow do painel) para `fixed`
> (relativo ao viewport, mesma ideia do tooltip genérico de `boards/show.blade.php`) — novo
> `positionFornecedorHistoryTooltip(event)` em `kanban.js` calcula `top`/`left` via
> `getBoundingClientRect()` do ícone no `@mouseenter`, guardado em `fornecedorHistoryPos`; o popover usa
> `:style` com esses valores e `z-50`, escapando do `overflow-y-auto` do corpo do card.
> Validado por HTTP: bundle JS confirmado com a string `preco-historico` na URL montada; `GET
> fornecedores/5/preco-historico` (fornecedor com 4 registros) segue retornando 200 com os dados corretos;
> markup confirmado com `class="fixed z-50 ..."` e `:style` ligado a `fornecedorHistoryPos`; classe
> Tailwind `.fixed{position:fixed}` conferida no bundle. `npm run build` e `pint --dirty` limpos.
- [x] Painel (`dashboard.blade.php`) reorganizado em 3 blocos + busca de cards em destaque: **Vencendo hoje**
      (novo, com selo de contagem e borda vermelha para destacar urgência), **Meus quadros** (já existia) e
      **Atualizados recentemente** (já existia). Campo de busca acima das estatísticas envia para
      `/cards?search=...` (mesmo parâmetro do filtro de "Todos os cards").

> `DashboardController::index()` ganhou `$dueTodayCards`: `Card::whereIn('board_id', $boardIds)
> ->whereNull('concluded_at')->whereDate('due_date', today())`, ordenado por prioridade
> (`orderByRaw("field(priority, 'alta', 'media', 'baixa')")`) — mesma regra de acesso a quadros já usada por
> `$boards`/`$recentCards` (admin/coordenador vê tudo; demais só quadros do pivot `user_board`), sem filtrar
> por responsável (mesma escolha já feita em `$recentCards`). View: busca é um `<form method="GET"
> action="{{ route('cards.index') }}">` com `<input name="search">` — mesmo nome de parâmetro que
> `CardController::index()` já usa para o `LIKE` no título. Bloco "Vencendo hoje" fica acima do grid de
> quadros/recentes (2 colunas), com destaque visual (borda superior vermelha, ícone, badge de contagem,
> badge de prioridade por card) para ficar com "dados de mais relevância em destaque"; estado vazio com
> mensagem de alívio ("Nenhum card vence hoje").
> Validado por HTTP: card de teste com `due_date` de hoje e prioridade alta apareceu no bloco (ordenado
> antes de um card seed de prioridade baixa, confirmando o `ORDER BY` de prioridade); contagem do selo
> refletiu 2 → 1 ao excluir o card de teste; busca `?search=TESTE` em `/cards` encontrou o card de teste
> criado a partir do dashboard; estado vazio confirmado ao zerar temporariamente o `due_date` do card seed
> restante (restaurado ao valor original depois). `npm run build` e `pint --dirty` limpos.
- [x] Ajuste de layout do painel: os 3 blocos ("Vencendo hoje" / "Meus quadros" / "Atualizados recentemente")
      passaram de empilhados (bloco 1 largura total + bloco 2/3 em 2 colunas) para **3 colunas lado a lado**
      (`grid grid-cols-1 lg:grid-cols-3`), cada uma com o mesmo cartão-contêiner (cabeçalho + lista com
      `divide-y` e `max-h-[28rem] overflow-y-auto`) para altura visual consistente entre as colunas — "Meus
      quadros" deixou de ser um grid de tiles 2×N e virou lista de linhas (ícone + nome + setor/contagem),
      no mesmo estilo das outras duas colunas.
> Validado por HTTP: markup confirma o grid único `lg:grid-cols-3` envolvendo as 3 colunas; classe Tailwind
> de valor arbitrário `max-h-[28rem]` conferida no bundle CSS; coluna "Meus quadros" renderizando como lista
> de linhas com ícone/cor do quadro. `npm run build` e `pint --dirty` limpos.
- [x] Listagem de quadros (`boards/index.blade.php`): a área `p-5 flex-1` do card (título, ícone, descrição,
      contagem de etapas/cards) virou um único `<a>` para `boards.show` — clicar em qualquer ponto dessa
      região (não só no título) abre o quadro. Botões do rodapé ("Abrir"/"Configurar"/"Editar"/"Excluir")
      continuam como ações separadas, sem mudança.
> Validado por HTTP: `<a href=".../quadros/{id}" class="block p-5 flex-1 hover:bg-surface/40 ...">` envolve
> corretamente todo o conteúdo do card (tags balanceadas, sem `<a>`/`<button>` aninhado dentro — o rodapé com
> os links de ação fica fora, em irmão separado); classe nova `hover:bg-surface/40` conferida no bundle CSS.
> `npm run build` e `pint --dirty` limpos.
- [x] Unificação do modal de card: a listagem global **"Todos os cards"** (`cards/index.blade.php`) usava um
      slide-over somente leitura (`cards/partials/detail-panel.blade.php`) bem mais simples que o modal do
      Kanban. Os dois agora usam **o mesmo componente** (`boards/partials/card-panel.blade.php` +
      novo `resources/js/card-panel.js`) — edição completa (título, descrição, Empresa, Fornecedor com
      histórico de preços, Evento, valores, campos configuráveis do quadro, prioridade/responsável/
      vencimento, anexos, comentários, histórico) nos dois lugares, com uma única fonte de verdade: alterar
      o modal agora vale para ambas as telas.

> `card-panel.js` (novo) extrai toda a lógica compartilhada de `kanban.js` (form, abas, quick-pickers,
> histórico de preços do fornecedor, salvar/excluir/comentar/anexar/transferir/concluir/reabrir/mover) como
> uma factory `cardPanel()`; `kanban.js` e o novo `cards-hub.js` fazem `{ ...cardPanel(), ...próprio }` e só
> definem os hooks opcionais (`afterCardSaved`, `afterCardRemoved`, `afterCardMoved`, `afterCardTransferred`,
> `afterCardConcluded`, `afterCardReopened`, `afterCardOpened`, `bumpCardCount`) chamados nos pontos de
> mutação: no Kanban eles atualizam o array reativo `columns` (sem reload); na listagem global, como a
> tabela é paginada e renderizada no servidor, eles simplesmente recarregam a página.
> Como a listagem global não é de um único quadro, `afterCardOpened` (só em `cards-hub.js`) busca sob
> demanda, por card: `cfg.transferBoards` (todos os quadros acessíveis exceto o do card, calculado de
> `cfg.boards`) e `columns` para a rail "Mover para fase" (reaproveitando `GET quadros/{board}/kanban`, já
> existente — sem rota nova). Os campos configuráveis do quadro deixaram de ser renderizados via Blade
> (`@foreach`/`@switch` em `$fields`, fixo por página) e passaram a ser 100% Alpine (`x-for`/`x-if` sobre
> `cfg.fields`), porque agora variam por card aberto — `openCard()` (compartilhado) sempre repõe
> `cfg.fields = c.board_fields` a partir do JSON do próprio card (`CardController::cardJson()` ganhou
> `required` nesse array, que faltava).
> Novo `App\Services\CardFormOptionsService::globalOptions()` (empresas/fornecedores/eventos/responsáveis —
> cadastros globais, não por quadro) reaproveitado por `BoardController::show()` e `CardController::index()`,
> eliminando a duplicação das 4 queries entre os dois controllers.
> Card concluído ganhou tratamento próprio no modal compartilhado (antes só existia no slide-over antigo):
> selo "Concluído" no cabeçalho, bloco "Reabrir e enviar para um quadro" (`doReopen()`, novo, mesmo padrão de
> `doTransfer()`) no lugar do bloco de transferência/conclusão quando `concludedAt` está presente.
> `cards/partials/detail-panel.blade.php` removido (não usado por mais nada).
> Validado por HTTP: criado card de teste com 3 campos configuráveis (textarea/select/checkbox) no quadro
> "Orçamentos" — `GET cards/{id}` confirma `board_fields` com `required` e `field_values` corretos; ciclo
> completo testado via API real (mesmos endpoints que os métodos compartilhados chamam): criar → editar
> (PUT) → mover → comentar → mover para coluna final → concluir → `GET` confirma `concluded_at`/`concluded_by`
> → reabrir em outro quadro → mover para coluna final do novo quadro → transferir para um terceiro quadro —
> todos 200/201 com as mensagens esperadas. Página `/cards` renderiza `cardsHub({...})` com
> `empresas`/`fornecedores`/`events`/`assignees`/`boards` embutidos (mesmo formato de `boards/show.blade.php`,
> via o novo service); `/quadros/{id}` inalterado na aparência, `kanban({...})` com a mesma config de antes.
> Bundle JS confirmado com os hooks/símbolos novos presentes. Dados de teste (card, campos do quadro)
> removidos ao final. `npm run build` e `pint --dirty` (8 arquivos) limpos.
- [x] Correção crítica: a unificação acima (`{ ...cardPanel(), ...próprio }` em `kanban.js`/`cards-hub.js`)
      quebrava os dois componentes por completo — Kanban parava de mostrar qualquer item
      (`Cannot read properties of undefined (reading 'find')` em `isFinalColumn`) e, em cascata, toda a
      página passava a lançar `ReferenceError` para qualquer propriedade (`viewMode`, `search`, `filters`,
      `columns`, etc. — literalmente tudo).

> Causa raiz: `{ ...cardPanel() }` (spread) **avalia os getters na hora da cópia** e copia só o valor
> resultante — não preserva o getter como acessor. `card-panel.js` tem vários `get` (computeds:
> `isFinalColumn`, `previousColumns`, `selectedAssignee` etc.); no instante do spread, esses getters rodam
> com `this` apontando para o objeto solto devolvido por `cardPanel()` — que ainda não tem `columns`/`cfg`
> (essas só existem depois de mescladas com o próprio objeto do host). `this.columns.find(...)` lança
> imediatamente, a função factory (`kanban(config)`/`cardsHub(config)`) nunca termina de construir seu
> objeto de retorno, e o `x-data` inteiro falha — daí o Alpine não ter NENHUM escopo de dados e todo o
> resto da página (mesmo propriedades sem relação nenhuma, tipo `viewMode`) virar `ReferenceError`.
> Fix: troca de spread por mesclagem de **descritores de propriedade**
> (`Object.defineProperties(alvo, Object.getOwnPropertyDescriptors(origem))`), que copia o getter em si
> (não o valor avaliado) — `card-panel.js` exporta `cardPanel(own)` (não mais um objeto solto pra espalhar);
> `kanban.js`/`cards-hub.js` chamam `return cardPanel({ ...seu próprio estado/métodos... })` no lugar de
> `return { ...cardPanel(), ... }`.
> Validado: reprodução isolada em Node confirma que o padrão antigo lança exatamente o erro reportado, e
> que o novo não lança; simulação completa do ciclo de vida real (`kanban(config)` → leitura de todos os
> getters antes do `init()` → `init()` roda e popula `columns` → getters lidos de novo) sem exceções, para
> `kanban.js` e `cards-hub.js`. Validado por HTTP de ponta a ponta de novo (criar card com campos
> configuráveis, `GET cards/{id}`, listagem global mostrando o card e o `openCard()` correto) — tudo OK.
> `npm run build` e `pint --dirty` limpos.

**Campo "Preço Interno" na categoria de fornecedor + linha de referência nos gráficos de preço.**
> Novo campo opcional `preco_interno` (decimal 15,2) em `fornecedor_categorias` (migration
> `2026_07_18_000006_...`), editável em `fornecedor-categorias/create` e `edit` (input com máscara de
> dinheiro BR via `x-mask:dynamic="$money($input, ',')"`, mesmo padrão do card-panel), validado/parseado
> via `Br::money()` nos Form Requests (Store/UpdateFornecedorCategoriaRequest). Exibido em
> `precos/categorias/show.blade.php` como badge "Preço Interno: R$ ...". Nos dois gráficos Chart.js de
> `precos/evolucao.blade.php` (Evolução e Comparação), quando a categoria selecionada tem preço interno
> cadastrado, um dataset extra tracejado (preto, sem pontos) é adicionado percorrendo toda a extensão do
> eixo X do gráfico (dois pontos: primeira e última data da série) — funciona como linha de referência
> fixa. Legenda passa a aparecer nos dois gráficos quando essa linha está presente (antes só a
> Comparação tinha legenda).
> Validado por HTTP: criado `preco_interno` via PUT no cadastro (formato BR "1.234,56" → persistido como
> `1234.56`), conferido o badge na tela de categoria, e conferido o payload JSON embutido nos dois
> gráficos (`evolucaoDatasets.push(...)`/`datasets.push(...)`) com os pontos corretos cobrindo a mesma
> janela de datas da série real. Dado de teste revertido para `null` ao final. `pint --dirty` e
> `npm run build` limpos.

**Coluna "Preço Interno" na listagem de categorias de fornecedor.**
> `fornecedor-categorias/index.blade.php`: nova coluna entre "Unidade" e "Fornecedores" mostrando
> `R$ 1.234,56` (ou "—" quando não cadastrado). Sem mudança no controller — `preco_interno` já vem no
> model, sem seleção restrita de colunas. Validado por HTTP (valor de teste `999.90` renderizado como
> "R$ 999,90"), revertido ao final. `pint --dirty` limpo.

**Aviso de "Valor previsto" vs. Preço Interno no modal de card.**
> No modal de card compartilhado (`boards/partials/card-panel.blade.php` + `card-panel.js`), ao sair do
> campo "Valor previsto" (`@blur`, não em tempo real — pedido explícito do usuário), o sistema compara o
> valor digitado com o Preço Interno da categoria do fornecedor selecionado no card e mostra uma mensagem
> logo abaixo do input: "Valor acima do Preço Interno da categoria (R$ ...)" em vermelho, ou "Valor dentro
> do Preço Interno da categoria (R$ ...)" em verde. Sem round-trip AJAX: `preco_interno` é estático por
> categoria (ao contrário do histórico de preços do fornecedor, que é uma série temporal e já usa
> `fornecedorPriceHistory`/`loadFornecedorHistory`), então foi embutido diretamente em `cfg.fornecedores`
> (`CardFormOptionsService::globalOptions()` agora faz eager-load de `categoria:id,preco_interno` e mapeia
> `preco_interno` — usado tanto pelo Kanban quanto pela listagem "Todos os cards", que compartilham essa
> config). `card-panel.js` ganhou `parseMoneyBR()` (inverso de `moneyFromDecimal`, converte a string
> mascarada BR de volta a número) e `checkEstimatedValueVsPrecoInterno()`, chamado só no `@blur` do
> input — o resultado fica em `estimatedValueCheck` (`{ above, message }` ou `null`), resetado ao
> abrir/criar um card. `quickFornecedor()` também passou a inicializar `preco_interno: null` no fornecedor
> recém-criado (sem categoria ainda).
> Validado: lógica de parsing/comparação replicada e testada isoladamente em Node (casos "1.234,56",
> "850,00", "900", vazio, `null`, valor igual ao preço interno — tudo correto). Validado por HTTP que
> `cfg.fornecedores` do quadro carrega `"preco_interno":850` para um fornecedor com categoria configurada.
> Dado de teste revertido ao final. `pint --dirty` e `npm run build` limpos.

**Correção crítica: `Br::money()` truncava valores BR sem casa decimal digitada (ex.: "90.000" virava 90,00).**
> Causa raiz: `Br::money()` tinha um atalho `if (is_numeric($value)) return (float) $value;` antes do
> parsing BR. Uma string como `"90.000"` (BR para noventa mil) também é um numeric string válido em PHP
> (`is_numeric("90.000")` é `true`, porque o PHP interpreta o "." como ponto decimal) — então o atalho
> disparava e devolvia `90.0` em vez de `90000.0`. Isso afetava qualquer valor BR com separador de milhar
> e sem parte decimal digitada: "Valor previsto"/"Valor realizado" no card, "Preço Interno" na categoria,
> lançamentos financeiros, registros de preço, submissões do formulário externo — todos os callers de
> `Br::money()` (`app/Http/Requests/Store|UpdateCardRequest`, `Store|UpdateFornecedorCategoriaRequest`,
> `FinancialEntryController`, `FinancialPlanController`, `PriceRecordController`,
> `ProcessExternalSubmission`).
> Fix: trocado `is_numeric($value)` por `is_float($value) || is_int($value)` — só pula o parsing BR
> quando o valor já chega como float/int genuíno (ex.: calculado em PHP), nunca para uma string, já que
> toda string vinda de request é sempre texto digitado pelo usuário no input mascarado, nunca um float cru.
> Validado: `Br::money('90.000')` → `90000.0`, `Br::money('10.000')` → `10000.0`, `Br::money('900,00')` →
> `900.0`, `Br::money('1.234,56')` → `1234.56`, `Br::money(90.5)` (float genuíno) → `90.5` — tudo correto.
> Validado por HTTP real (Puppeteer + Chrome headless): criado card com "Valor previsto" = "90.000" via
> o modal real, confirmado no banco que `estimated_value` persistiu como `90000.00` (não `90.00`), e que
> reabrir o card reexibe corretamente "90.000,00".
>
> Nesse mesmo ciclo de depuração, confirmou-se (também via Puppeteer real, não só leitura de código) que
> o `@blur="checkEstimatedValueVsPrecoInterno()"` funciona corretamente — o problema relatado era o
> bundle do navegador estar desatualizado em relação ao código-fonte (edição em `resources/js/` exige
> `npm run build` — Blade/PHP aplicam na hora, JS bundlado não).
>
> **Incidente à parte, causado durante essa investigação**: rodar `php artisan test` usou o MESMO banco
> do `.env` (`upmusic_local`, não existe `.env.testing`/config de teste separado) — `RefreshDatabase`
> apagou todas as tabelas do banco de desenvolvimento. A suíte já falhava antes de qualquer teste rodar,
> por um bug de ordenação de migrations pré-existente e nunca notado: `add_event_id_to_external_forms_table`
> (datada `2026_07_16`) referencia `events` via FK, mas `create_events_table` é `2026_07_17` — roda DEPOIS.
> Isso nunca dava erro no dia a dia porque as migrations eram aplicadas incrementalmente (uma de cada vez,
> conforme os arquivos eram criados), só se manifestando numa migration 100% do zero. Corrigido renomeando
> o arquivo para `2026_07_17_000004_add_event_id_to_external_forms_table.php` (depois de `create_events_table`).
> Restaurado com `php artisan migrate:fresh --seed`. Isso expôs mais um bug pré-existente e não relacionado:
> `database/seeders/SampleDataSeeder.php` ainda usava a coluna `category` (string) em `Fornecedor` e o
> model `App\Models\Service` — ambos removidos há tempos pelo refactor de banco de preços/categorias
> (`fornecedor_categoria_id` FK + tabelas `services`/`service_prices` dropadas). Corrigido para popular
> `fornecedor_categoria_id` via `FornecedorCategoria::where('nome', ...)` e removida a seção de `Service`.
> Nenhum dado de produção existia a perder (ambiente `upmusic_local` é só dev local, populado via seed) —
> mas fica o alerta: **nunca rodar `php artisan test` neste projeto sem antes configurar um banco de teste
> separado** (`.env.testing` com SQLite `:memory:` ou um schema MySQL dedicado), já que o `phpunit.xml`
> tem a config de sqlite comentada e o projeto não tem `.env.testing`.

**Tag "Vencido" no modal de card quando o prazo já passou.**
> No modal compartilhado (`card-panel.js` + `boards/partials/card-panel.blade.php`), quando o `due_date`
> do card é anterior a hoje, aparece um badge vermelho "Vencido" no cabeçalho (ao lado do título, no mesmo
> padrão do badge "Concluído"). Novo getter `get isOverdue` em `card-panel.js`: compara `form.due_date`
> (formato `Y-m-d`, igual ao que vem do backend) com a data de hoje via comparação lexical de string —
> sem parsing/fuso. Um card concluído não é marcado como vencido (o header já mostra "Concluído" e o card
> não está mais em aberto). Como o componente é compartilhado, vale tanto no Kanban quanto em "Todos os
> cards".
> Validado por HTTP real (Puppeteer + Chrome headless): card com prazo 3 dias atrás → badge "Vencido"
> visível (`isOverdue: true`); card com prazo futuro → sem badge; card concluído com prazo vencido →
> só "Concluído", sem "Vencido" (`isOverdue: false`). Sem erros de console. Dados de teste revertidos ao
> final. `pint --dirty` e `npm run build` limpos.

**Destaque de card vencido no Kanban (data em vermelho + tooltip "Vencido").**
> Nos cards do quadro (board e lista), quando o prazo (`due_date`) é anterior a hoje, a data aparece em
> vermelho/negrito e o card mostra o tooltip "Vencido" ao passar o mouse. Backend: `CardPresenter::compact()`
> ganhou o estado `overdue` no `due_status` (`match`: `isToday` → today, `isPast` → overdue, `isTomorrow`
> → tomorrow) — a ordem importa (`isToday` antes de `isPast`, senão hoje cairia em overdue). Front
> (`kanban.js`): `dueTooltipText` mapeia `overdue: 'Vencido'`; novo helper `dueDateClass(card)` devolve
> `text-red-600 font-semibold` quando vencido; `dueBadgeMeta` ganhou `overdue: { danger, 'Vencido' }` para
> o badge da visão Lista. Blade (`boards/show.blade.php`): a data do card (board e lista) aplica
> `:class="dueDateClass(card)"`. Cards concluídos não entram (são removidos do quadro).
> Validado por HTTP real (Puppeteer + Chrome headless), board e lista: card vencido (prazo -3d) → data
> `rgb(220,38,38)` (`text-red-600 font-semibold`) + tooltip/badge "Vencido"; card de hoje → inalterado
> ("Vence hoje"); card futuro → sem destaque. Sem erros de console. Dados de teste revertidos.
> `pint --dirty` e `npm run build` limpos.

**Captura Rápida de Orçamentos e NFs — Fase 1 (Backend + Canal B in-app).** Ver [specs/16](16-captura-rapida-orcamentos-nf.md).
> Implementada a Fase 1 completa: qualquer usuário autenticado ativo consegue enviar um PDF/imagem de
> orçamento ou NF pela tela "Captura rápida" (sem PWA/Atalho — isso é Fase 2/3) e transformá-lo em card com
> o anexo já vinculado, em 2 telas.
> - **Migration** `card_captures` (staging: `user_id`, `board_id`/`card_id` nullable, `kind`, `source`,
>   `status`, `original_name`, `path`, `mime`, `size`, `suggested_title`).
> - **Enums**: `AttachmentKind::Orcamento`, `CardOrigin::CapturaRapida` (novos casos nos enums existentes,
>   sem migration — colunas já são `varchar(20)`); novos `CaptureStatus` e `CaptureSource`.
> - **Model** `CardCapture` (+ scope `pending()`); **Policy** `CardCapturePolicy` — primeira autorização por
>   **dono** do projeto (`$capture->user_id === $user->id`), diferente do padrão existente (role/quadro).
> - **Action** `ProcessQuickCapture`: resolve o quadro, cria o card via `CreateCard` (mesma Action do
>   restante do sistema, com `$actor` = usuário autenticado, `origin = captura_rapida`), move o arquivo de
>   staging (`capturas/{user}/...`) para `card-attachments/{card_id}/` e cria o anexo (`kind` Orçamento/NF).
> - **Form Requests** `QuickUploadRequest` (múltiplos arquivos, mesmos limites de `CardController`:
>   `max:10240`, `mimes:pdf,jpg,jpeg,png,webp`) e `ConfirmCaptureRequest` (valida `board_id` contra
>   `canAccessBoard()`, `estimated_value` via `Br::money()`).
> - **Controller** `CaptureController` (`index`/`upload`/`show`/`preview`/`store`/`destroy`) + rotas em
>   `routes/web.php` (grupo `auth`+`active`, sem restrição de role — é ferramenta pessoal).
> - **Views**: Caixa de Entrada (`captures/index.blade.php`, com `x-empty-state`), formulário de
>   arrastar/soltar (`captures/partials/upload-form.blade.php` — **componente novo**, não existia dropzone
>   no projeto) e tela de confirmação (`captures/show.blade.php`: prévia do arquivo, tipo, quadro com
>   default = último usado em sessão, campos opcionais recolhíveis).
> - **Menu**: item "Captura rápida" no `sidebar.blade.php`.
> - **Comando agendado** `captures:prune` (diário, via Scheduler já existente) remove capturas `pendente`
>   com mais de 7 dias e seus arquivos.
> - **Extra**: `kanban.js` ganhou `openCardFromQueryString()` — ao ser redirecionado do fluxo de captura
>   para o board (`?abrir_card=ID`), o card criado já abre automaticamente no modal.
>
> **Bug encontrado e corrigido durante a validação**: `upload-form.blade.php` usava
> `$errors->get('arquivos.*')`, que o Laravel agrupa por chave real (`arquivos.0`, `arquivos.1`...) — um
> array de arrays, incompatível com `x-input-error` (que espera lista simples de strings), causando
> `500 htmlspecialchars(): Argument #1 ($string) must be of type string, array given`. Corrigido trocando
> para `$errors->all()` (o form só tem esse campo, então é equivalente e evita o wildcard).
>
> Validado por HTTP real de ponta a ponta: upload de 1 arquivo → confirmação → card criado na coluna
> `is_entry`, `origin=captura_rapida`, anexo com `kind` e `uploaded_by` corretos, `estimated_value` BR
> parseado certo ("1.500,00" → 1500.00), arquivo movido de staging para `card-attachments/{id}/`, captura
> marcada `processado`; upload de múltiplos arquivos direciona para a Caixa de Entrada; descarte remove
> registro e arquivo; **isolamento por dono confirmado** (usuário B recebe 403 ao tentar ver/descartar
> captura do usuário A); upload de arquivo inválido não cria registro (após corrigir o bug acima); comando
> `captures:prune` remove só a captura pendente antiga, preservando as recentes, e apaga o arquivo do disco.
> Dados de teste limpos ao final. `pint --dirty` e `npm run build` limpos.
>
> **Fora desta entrega (Fases 2/3, ver spec 16)**: PWA/Web Share Target (Android), Atalho iOS + token
> Sanctum + URL assinada — nenhuma rota pública/isenta de CSRF foi criada nesta fase.

**Captura Rápida de Orçamentos e NFs — Fase 2 (PWA + Web Share Target no Android).** Ver [specs/16](16-captura-rapida-orcamentos-nf.md#5-como-funciona-o-canal-a-compartilhar-do-whatsapp).
> Com o upMusic instalado como PWA no Android, ele passa a aparecer na folha de compartilhamento nativa —
> compartilhar um PDF do WhatsApp abre o app já com o arquivo em staging, sem custo adicional (recursos
> padrão da web).
> - **Ícones PWA** (`public/img/pwa-192.png`, `pwa-512.png`, `maskable`) gerados a partir do símbolo da
>   marca (`favicon-up.png` — três barras laranjas, não a wordmark horizontal) centralizado sobre fundo
>   preto sólido (mesmo `background_color` do manifest), via script PHP/GD descartável.
> - **`public/manifest.webmanifest`**: `display: standalone`, cores da marca, e o bloco `share_target`
>   apontando para `/captura/receber`. Campo de arquivo declarado como `"arquivos[]"` (com colchetes) —
>   necessário para o PHP bucketizar múltiplos arquivos compartilhados em array (`$_FILES`); sem os
>   colchetes, PHP mantém só o último arquivo em compartilhamentos com mais de um.
> - **`public/sw.js`**: Service Worker mínimo (install/activate/fetch) só para satisfazer o critério de
>   instalabilidade do Chrome/Android — sem cache/offline neste MVP.
> - **`resources/js/pwa.js`** (novo, importado em `app.js`): registra o Service Worker e controla o banner
>   "Instalar app" via evento `beforeinstallprompt` (com dispensa persistida em `localStorage`).
> - **`layouts/app.blade.php`**: `<link rel="manifest">`, `theme-color`, `apple-touch-icon`, e o banner de
>   instalação abaixo do topbar.
> - **Backend**: `CaptureController` refatorado — `upload()` (Canal B) e o novo `receive()` (Canal A/Android)
>   compartilham os métodos privados `storeCaptures()`/`respondToCaptures()`; `receive()` grava
>   `source = pwa_share`. Rota `POST /captura/receber` **isenta de CSRF**
>   (`VerifyCsrfToken::$except`) — o POST é disparado pelo sistema operacional, sem token CSRF — mas
>   continua exigindo sessão autenticada (dentro do grupo `auth`+`active`) e a mesma validação estrita de
>   upload da Fase 1. Só estaciona arquivo; nenhuma ação destrutiva.
> Validado por HTTP real: manifest/SW/ícones acessíveis; **POST simulando o Web Share Target sem token
> CSRF, só com cookie de sessão, funciona** (302, não o `419` de CSRF mismatch); mesmo POST sem sessão
> redireciona para `/login` (limitação conhecida documentada na spec — sessão expirada perde o arquivo);
> múltiplos arquivos no share vão para a Caixa de Entrada; capture registrada com `source = pwa_share`;
> fluxo completo (captura → confirmação → card) sem regressão após o refactor do controller. Dados de teste
> limpos ao final. `pint --dirty` e `npm run build` limpos.
>
> **Fora desta entrega (Fase 3)**: Atalho iOS, token pessoal Sanctum, URL assinada temporária — o Android
> já está coberto; falta só o iPhone.

**Captura Rápida de Orçamentos e NFs — Fase 3 (Atalho iOS + token Sanctum + URL assinada).** Ver [specs/16](16-captura-rapida-orcamentos-nf.md#52-ios--atalho-da-apple-shortcut) (fecha a spec inteira: Fases 1, 2 e 3 concluídas).
> Fecha a lacuna do iPhone: o Atalho da Apple (montado e testado em aparelho real nesta sessão — receita
> completa no Apêndice A da spec e replicada na tela "Configurar iPhone") agora consegue enviar um arquivo
> ao upMusic **sem nenhuma sessão de navegador**, autenticado só por um token pessoal.
> - **`CaptureTokenController`** (novo): `edit()` renderiza "Configurar iPhone"; `store()` gera um token
>   Sanctum (`$user->createToken('captura-ios', ['capture:create'])`, revogando o anterior — só um por
>   usuário) e devolve o texto puro **uma única vez** via flash de sessão; `destroy()` revoga.
> - **`CaptureController::receive()`**: passou a diferenciar o request por `$request->user()->currentAccessToken()`
>   — `null` = autenticado por sessão (Android, `source = pwa_share`), presente = autenticado por token
>   (iOS, `source = ios_shortcut`). Checa manualmente `tokenCan('capture:create')` só quando há token
>   (não dá pra usar o middleware `abilities:` do Sanctum direto na rota, porque ele bloquearia as
>   requisições autenticadas por sessão, que não têm token nenhum). Quando via token, responde `200 JSON
>   { confirm_url }` com `URL::temporarySignedRoute('captures.show', ...)` (30 min) em vez do redirect
>   302 usado para sessão.
> - **`CaptureController::show()`**: quando chega **sem usuário e com assinatura válida**
>   (`hasValidSignature()`), autentica a aba do Safari como o dono da captura via `Auth::login($capture->user)`
>   \+ `session()->regenerate()` — um "magic link" de uso único. A partir daí a navegação (inclusive
>   `captures.store`, que continua dentro do grupo `auth` normal) segue com sessão comum: CSRF, Policy e
>   tudo mais funcionam sem nenhum código especial adicional.
> - **Rotas**: `captura/receber` e `capturas/{capture}` (show) saíram do grupo `Route::middleware(['auth','active'])`
>   porque esse `auth` bloquearia/redirecionaria a requisição antes mesmo do controller rodar a lógica de
>   token/assinatura. `captura/receber` usa `auth:web,sanctum` (tenta sessão, senão token) +
>   `throttle:20,1`; `capturas/{capture}` usa só `active` (seguro mesmo sem usuário — é no-op nesse caso).
>
> **Bug de rotas encontrado e corrigido durante a validação**: `DELETE capturas/token` retornava `404`
> porque `DELETE capturas/{capture}` (`captures.destroy`, já existente da Fase 1) tinha sido registrada
> **antes** — o Laravel tentava casar `{capture}` = `"token"` primeiro, e o route-model-binding implícito
> falhava com 404 antes de sequer chegar no `CaptureTokenController`. Mesma categoria de bug que o projeto
> já tinha um comentário alertando para `quadros/{board}` ("rotas literais antes do wildcard") — só que
> dessa vez o alerta não existia ainda para `capturas/`. Corrigido reordenando: `configurar-iphone`,
> `capturas/token` (POST/DELETE) agora vêm antes de `capturas/{capture}/...`.
>
> Validado por HTTP real, de ponta a ponta, **sem nenhuma sessão pré-existente** (simulando o Atalho do
> zero absoluto): `POST /captura/receber` com `Authorization: Bearer <token>`, zero cookies, zero CSRF →
> `200 { confirm_url }`; `GET confirm_url` sem cookies → `200`, sessão nova criada automaticamente
> (auto-login); `POST criar-card` usando só essa sessão recém-criada (sem token, sem login manual) →
> `302`, card criado com `origin=captura_rapida`, `created_by`/`uploaded_by` corretos (o dono do token, não
> quem abriu o link). Também validado: token inválido/revogado → `401 {"message":"Unauthenticated."}`
> (só com header `Accept: application/json` — documentado na receita do Atalho, senão vira redirect HTML
> para `/login`); token sem a ability `capture:create` → `403`; assinatura adulterada na URL → `403`;
> revogar token via "Configurar iPhone" realmente invalida o token (retestado depois da correção do bug de
> rotas). Dados de teste (cards, capturas, tokens) removidos ao final. `pint --dirty` limpo (sem mudança
> de JS nesta fase).
>
> **Com isso, a spec 16 está com as 3 fases implementadas**: Fase 1 (backend + Captura Rápida in-app,
> universal), Fase 2 (PWA + Web Share Target no Android) e Fase 3 (Atalho iOS + token). Restam só os itens
> já marcados como fora de escopo na própria spec (OCR, e-mail inbound, Web Push, app nativo pago).

**Menu de ações do card (3 pontos): Duplicar, Arquivar e Excluir movido para dentro do menu.**
> No cabeçalho do modal de card (`card-panel.blade.php`), ao lado do botão de fechar, novo menu suspenso
> (ícone de 3 pontos verticais, mesmo padrão Alpine hand-rolled já usado em Responsável/Vencimento/
> Prioridade/Fornecedor) com "Duplicar Card", "Arquivar"/"Desarquivar" e "Excluir" — este último removido
> do rodapé do modal, que agora só tem Fechar/Salvar.
> - **Arquivamento**: segue o mesmo padrão de `concluded_at`/`concluded_by` (não um enum de status) —
>   migration adiciona `archived_at`/`archived_by` (FK `users`, nullable) em `cards`; `Card` ganhou
>   `archivedBy()` e `scopeArchived()`; `ArchiveCard`/`UnarchiveCard` (novas Actions, espelham
>   `ConcludeCard`/`ReopenCard`) registram movimentação (`MovementType::Archival`/`Unarchival`, novos casos).
>   Card arquivado some do Kanban (`BoardController::kanbanData()` ganhou `whereNull('archived_at')`, ao
>   lado do `whereNull('concluded_at')` já existente) mas continua visível/gerenciável em "Todos os cards"
>   (novo filtro de status "Arquivados" + badge cinza).
> - **Duplicação**: nova Action `DuplicateCard` compõe `CreateCard` — copia campos fixos e valores dos
>   campos configuráveis (via `fieldValues`), mantém o card na mesma coluna do original, título vira
>   `"{original} [CÓPIA]"` (truncado para caber no limite de 180 caracteres da coluna). Não copia anexos,
>   comentários, histórico nem estado de conclusão/arquivamento.
> - **Rotas**: `POST cards/{card}/duplicar`, `/arquivar`, `/desarquivar` — autorização via
>   `authorize('update', $card)`, mesmo padrão de concluir/reabrir/transferir (`CardPolicy` não ganhou
>   métodos novos).
> - **JS**: `card-panel.js` ganhou `archivedAt`/`archivedBy`/`actionsMenuOpen` e os métodos
>   `duplicate()`/`doArchive()`/`doUnarchive()` (confirmação via SweetAlert2 em duplicar/arquivar, sem
>   confirmação em desarquivar — mesmo padrão de `doConclude()`); `kanban.js` ganhou
>   `afterCardArchived()`/`afterCardDuplicated()` para atualizar o array reativo sem reload;
>   `cards-hub.js` ganhou os três hooks equivalentes, todos via reload de página (padrão já existente ali).
>
> Validado por HTTP real (card de teste criado via tinker, removido ao final): duplicar → card novo na
> mesma coluna com "[CÓPIA]" no título; arquivar → some do `GET quadros/{id}/kanban`, segunda tentativa de
> arquivar retorna `422`; desarquivar → volta a aparecer no Kanban, movimentação `unarchival` registrada
> corretamente no histórico; excluir (agora só acessível pelo menu) segue funcionando via soft delete.
> `pint --dirty` e `npm run build` limpos.

---

### Status por fase
| Fase | Descrição | Modelo | Status |
|-----:|-----------|--------|--------|
| 0 | Setup | `sonnet` | ☑ Concluída |
| 1 | Design system | `sonnet` | ☑ Concluída |
| 2 | Banco de dados | `sonnet` | ☑ Concluída |
| 3 | Auth e permissões | `sonnet` | ☑ Concluída |
| 4 | Cadastros base | `sonnet` | ☑ Concluída |
| 5 | Quadros/departamentos | `sonnet` | ☑ Concluída |
| 6 | Kanban e cards | `opus` | ☑ Concluída |
| 7 | Templates | `opus` | ☑ Concluída |
| 8 | Formulário externo | `opus` | ☑ Concluída |
| 9 | Planejamento financeiro | `opus` | ☑ Concluída |
| 10 | Banco de preços | `sonnet` | ☑ Concluída |
| 11 | Refino e entrega | `sonnet` | ☑ Concluída |
| 12 | Futuro (WhatsApp) | `sonnet` | ⊘ Fora do escopo do MVP |

> Atualize o status (☐ Não iniciada / ◐ Em andamento / ☑ Concluída / ⊘ Fora do escopo do MVP) conforme o avanço.
