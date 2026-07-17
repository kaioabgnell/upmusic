# 02 — Design System

> **Modelo recomendado:** `sonnet` (Sonnet 5).

Base de tokens completa em [`../DESIGN.md`](../DESIGN.md). Este documento define como aplicar a
identidade da **Up Music** (preto + laranja) sobre aquela base e as regras obrigatórias de UI.

## 1. Marca

- **Cores da marca:** preto `#000000` e laranja `#ff8c1e`.
- **Logos** (em `referencia/`, copiar para `public/img/`):
  - `LOGO UP.png` — fonte **preta**: usar em fundos claros (sidebar clara, telas de conteúdo, formulário externo).
  - `LOGO UP - VS 2.png` — fonte **branca**: usar em fundos escuros (topbar/sidebar preta, telas de login com fundo escuro).
- **Sem emojis** em qualquer superfície do produto.

## 2. Paleta (mapeada sobre DESIGN.md)

| Papel | Token | Valor |
|-------|-------|-------|
| Primária (ações, texto forte, superfícies escuras) | `primary` / `ink` | `#000000` |
| Sobre primária | `on-primary` | `#ffffff` |
| **Accent (CTAs, estado ativo, destaques)** | `brand-orange` | `#ff8c1e` |
| Accent pressionado | `brand-orange-deep` | `#fa810f` |
| Accent suave (tint de fundo/sucesso) | `brand-orange-soft` | `#f9a14f` |
| Canvas | `canvas` | `#ffffff` |
| Superfície | `surface` | `#f7f7f7` |
| Hairline (bordas/divisores) | `hairline` | `#e5e5e5` |
| Texto secundário | `steel` | `#5a5a5c` |
| Erro | `brand-error` | `#d45656` |

Regra de accent: o laranja é **reservado** para CTAs primários, estado ativo (aba/coluna/nav) e
destaques pontuais. Não usar laranja em grandes superfícies ou em texto de corpo.

## 3. Tipografia

- Família principal **Inter** (UI, títulos, corpo). Sem itálico; ênfase por peso/cor.
- Hierarquia e tokens conforme `DESIGN.md` (`hero-display` → `heading-1..5` → `body-md/sm` → `caption/micro`).
- Corpo padrão `body-md` (16px, line-height 1.5). Rótulos de tabela/nav em `body-sm`.

## 4. Ícones — Font Awesome (obrigatório)

- Toda iconografia via Font Awesome 6 (https://fontawesome.com/). Nunca emojis, nunca ícones de outra fonte.
- Convenções sugeridas: quadros `fa-columns`/`fa-table-columns`, empresas `fa-building`, fornecedores
  `fa-truck-field`/`fa-user-tie`, financeiro `fa-chart-line`, preços `fa-tags`, usuários `fa-users`,
  setores `fa-sitemap`, anexo `fa-paperclip`, mover `fa-arrow-right`, adicionar `fa-plus`, filtro `fa-filter`.
- Instalar self-hosted (npm `@fortawesome/fontawesome-free`) para o formulário externo funcionar offline/sem CDN.

## 5. Alertas e diálogos — SweetAlert2 (obrigatório)

Todo feedback de sistema usa SweetAlert2 (https://sweetalert2.github.io/). Padrões:

- **Sucesso:** toast no canto superior (timer ~2,5s) após salvar/mover.
- **Confirmação destrutiva** (excluir, mover para outro departamento): modal `confirm` com botão de confirmação
  em laranja (`#ff8c1e`) e cancelar neutro. Nunca excluir sem confirmação.
- **Erro de validação/servidor:** modal `error` com mensagem clara.
- **Loading:** `Swal.showLoading()` em operações assíncronas longas.
- Centralizar num helper JS (`resources/js/alerts.js`) com `notifySuccess`, `confirmAction`, `notifyError`.

## 6. Referência de layout — Pipefy (`referencia/pipefy.png`)

Elementos a reproduzir na experiência:

- **Topbar** com nome do quadro/projeto, ícone e ações à direita (compartilhar formulário, gerenciar).
- **Barra de abas** do quadro: Mapa / Fluxo / **Kanban** / Lista / Relatórios / Formulário (adaptar ao escopo).
- **Kanban** com colunas de largura fixa, cabeçalho de coluna com nome + contador de cards, cards empilhados.
- Botão **"+ Nova fase" / "Adicionar nova coluna"** sempre ao final das colunas.
- **Busca de cards** e **filtro** (ex.: por empresa) no topo do quadro, alinhados à direita.
- **Card compacto** na coluna (título + campos-resumo) e **card de detalhe** ao clicar (formulário completo em painel/modal).
- Botão **"+ Criar novo card"** fixo no rodapé da primeira coluna (ou topbar), estilo Pipefy.

## 7. Padrões SaaS de tela

- **Layout app:** sidebar de navegação à esquerda (Quadros, Cadastros, Financeiro, Preços, Usuários) + topbar.
  Sidebar escura com logo branca, ou clara com logo preta — escolher uma e manter consistente.
- **Listagens (index):** título + botão primário "Novo …" à direita; barra de busca + filtros; tabela paginada;
  ações por linha (ver/editar/excluir) com ícones FA.
- **Criação/edição:** formulário em página ou painel lateral; botões "Salvar" (primário) e "Cancelar" à direita/rodapé.
- **Fluxo de criação respeita dependências do banco:** ao criar um Card, a Empresa já deve existir (com atalho
  "cadastrar empresa" inline via modal); ao criar um Quadro, o Setor já deve existir; etc. (ver [03](03-modelo-de-dados.md)).
- **Filtros e relacionamentos** sempre via selects com busca (empresa, setor, responsável), nunca campo livre para FK.
- **Responsivo:** desktop (sidebar fixa), tablet (sidebar recolhível), mobile (sidebar em drawer; Kanban com scroll
  horizontal por coluna). Ver breakpoints em `DESIGN.md`.

## 8. Componentes Blade a criar

`x-app-layout`, `x-guest-layout`, `x-public-layout`, `x-sidebar`, `x-topbar`, `x-page-header`,
`x-data-table`, `x-form.input`, `x-form.select`, `x-form.money`, `x-form.file`, `x-badge`,
`x-kanban.board`, `x-kanban.column`, `x-kanban.card`, `x-card-detail`, `x-empty-state`.

## 9. Critérios de aceite (design)

- [ ] Nenhum emoji em telas, seeds ou mensagens.
- [ ] Todos os ícones são Font Awesome.
- [ ] Todos os alertas/confirmações usam SweetAlert2.
- [ ] Laranja usado só em accent/CTAs/estado ativo.
- [ ] Logos corretas por contraste de fundo.
- [ ] Kanban com "adicionar nova coluna" ao final e busca/filtro no topo, fiel ao Pipefy.
- [ ] Layout responsivo em desktop/tablet/mobile.
