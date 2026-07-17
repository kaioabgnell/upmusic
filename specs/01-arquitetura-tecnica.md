# 01 — Arquitetura Técnica

> **Modelo recomendado:** `sonnet` (Sonnet 5).

## 1. Stack

| Camada | Tecnologia |
|--------|-----------|
| Linguagem | PHP 8.1 |
| Framework | Laravel 10.x |
| Banco | MySQL (`upmusic_local`, já existente) |
| Frontend | Blade + Tailwind CSS + Alpine.js, bundling com Vite |
| Drag & drop | SortableJS |
| Ícones | Font Awesome 6 |
| Alertas/diálogos | SweetAlert2 |
| Auth base | Laravel Breeze (Blade), re-estilizado ao design da marca |
| HTTP client (front) | `fetch`/Axios para chamadas AJAX do Kanban |

## 2. Princípios de arquitetura limpa

O objetivo é isolar **regras de negócio** de framework e de detalhes de I/O, mantendo controllers finos
e models sem lógica. Camadas:

```
Presentation  →  Application  →  Domain  ←  Infrastructure
(HTTP/Blade)     (Actions/         (Enums/      (Eloquent models,
                  Services/         DTOs/        Repositories concretos,
                  Requests)         contratos)   Storage, Mail)
```

- **Domain** — enums, DTOs, e interfaces de repositório. Sem dependência de Laravel quando possível.
- **Application** — Actions (casos de uso de escrita) e Services (orquestração/leitura), Form Requests, Policies.
- **Infrastructure** — Eloquent models, implementações de repositórios, adapters (storage, e-mail, WhatsApp).
- **Presentation** — Controllers (finos), rotas, Blade views, componentes, view composers.

Regras:
- Controller **não** contém regra de negócio nem validação — delega para Action/Service e recebe Form Request.
- Model Eloquent **não** contém regra de negócio complexa — só relacionamentos, casts, scopes.
- Toda escrita de fluxo relevante passa por uma **Action** (`app/Actions/...`) com um método `execute()`/`handle()`.
- Transações de banco (`DB::transaction`) envolvem operações multi-tabela (ex.: importar template, mover card com histórico).

> Pragmatismo: repositórios são usados onde agregam (consultas complexas, testabilidade). Para CRUD
> simples, Eloquent direto no Service é aceitável. Não criar abstração sem ganho.

## 3. Estrutura de pastas (proposta)

```
app/
  Actions/            # casos de uso de escrita (CreateCard, MoveCard, ImportTemplate, ...)
    Cards/
    Boards/
    Financial/
  Domain/
    Enums/            # UserRole, FornecedorTipo, FieldType, CardOrigin, ...
    DTOs/
  Http/
    Controllers/
    Requests/         # Form Requests (validação)
    Resources/        # API Resources (JSON do Kanban)
    Middleware/       # EnsureRole, ...
  Models/             # Eloquent
  Policies/
  Services/           # leitura/orquestração (BoardService, FinancialReportService, ...)
  Support/            # helpers, formatters (CNPJ/CPF/moeda)
database/
  migrations/
  seeders/
resources/
  views/
    layouts/          # app, guest, public
    components/       # blade components (kanban-card, sidebar, ...)
    boards/ cards/ empresas/ fornecedores/ financeiro/ precos/ external/
  js/
    kanban.js         # SortableJS + AJAX
    app.js
  css/
    app.css           # Tailwind
routes/
  web.php             # app autenticado
  public.php          # formulário externo (sem auth)
```

## 4. Performance

- **Eager loading** obrigatório em listagens (evitar N+1): `with([...])`. Rodar `Model::preventLazyLoading()` em não-produção.
- **Índices** em todas as FKs e colunas de filtro/ordenação (ver [03-modelo-de-dados.md](03-modelo-de-dados.md)).
- **Paginação** em toda listagem; Kanban carrega cards por quadro com limite/paginação por coluna quando necessário.
- **Cache** de dados de baixa mutação (setores, definições de campos do quadro) via `cache()->remember`.
- Colunas `posicao`/`ordem` inteiras para ordenação O(1) de drag-and-drop; reordenar em lote numa transação.
- Consolidação financeira via queries agregadas (SUM) no banco, não em PHP.
- Assets minificados via `npm run build`; imagens de logo otimizadas em `public/img`.

## 5. Padrões de código

- PSR-12; `php artisan pint` para formatação.
- Nomes de tabela/coluna em inglês plural (padrão Laravel); rótulos de UI em PT-BR.
- Enums PHP nativos para valores fixos de domínio.
- Respostas JSON via API Resources; respostas de página via Blade.
- Timezone `America/Sao_Paulo`; locale `pt_BR`; moeda BRL.

## 6. Setup inicial (ordem)

1. `composer create-project laravel/laravel:^10.0 .` (ou instalar Laravel 10 no diretório).
2. Configurar `.env` com a conexão do banco (ver [`.claude/CLAUDE.md`](../.claude/CLAUDE.md)).
3. `php artisan key:generate`.
4. Instalar Breeze (Blade) e re-estilizar; instalar Tailwind/Alpine/Vite.
5. Adicionar Font Awesome e SweetAlert2 (via npm ou CDN self-host).
6. Copiar logos de `referencia/` para `public/img/`.
7. Criar migrations conforme [03](03-modelo-de-dados.md) e `php artisan migrate --seed`.
8. Implementar módulos na ordem do [CHECKLIST](CHECKLIST.md).

## 7. Critérios de aceite (arquitetura)

- [ ] Controllers sem regra de negócio nem validação inline.
- [ ] Nenhuma query N+1 nas telas de listagem/Kanban (verificado com `preventLazyLoading`).
- [ ] Todas as FKs com índice e constraint de integridade referencial.
- [ ] Operações multi-tabela em transação.
- [ ] `.env` usando `upmusic_local` sem recriar o banco.
