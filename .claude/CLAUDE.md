# upMusic — Sistema de Gestão de Processos Internos

Contexto e convenções do projeto para o Claude Code. Leia as specs completas em [`specs/`](../specs/README.md) antes de implementar qualquer módulo.

## O que é

Plataforma web (Laravel + MySQL) que substitui o controle em Excel da Up Music por um
sistema centralizado e colaborativo. O núcleo é um **quadro Kanban de processos** inspirado
no Pipefy, onde cada **quadro representa um departamento** e os **cards** percorrem etapas
configuráveis, podendo transitar entre departamentos. Complementam o núcleo: cadastros base,
templates de cards, planejamento financeiro (previsto x realizado), banco de preços histórico
e um formulário externo público para clientes.

## Stack

- **Backend:** PHP 8.1 + Laravel 10 (última minor 10.x — Laravel 11 exige PHP 8.2).
- **Banco:** MySQL (o banco `upmusic_local` já existe e está vazio — criar schema só via migrations).
- **Frontend:** Blade + Tailwind CSS + Alpine.js + Vite. Drag-and-drop do Kanban com SortableJS.
- **Ícones:** Font Awesome (https://fontawesome.com/) — nunca usar emojis.
- **Alertas/diálogos:** SweetAlert2 (https://sweetalert2.github.io/) para todo feedback, confirmação e toast.
- **Arquitetura:** limpa/em camadas com foco em performance (ver [`specs/01-arquitetura-tecnica.md`](../specs/01-arquitetura-tecnica.md)).

## Conexão do banco (.env)

Não criar o banco — ele já existe. Usar exatamente:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=upmusic_local
DB_USERNAME=root
DB_PASSWORD=
```

Cliente MySQL local para inspeção manual: `/Applications/XAMPP/xamppfiles/bin/mysql -h127.0.0.1 -uroot upmusic_local`
(o `mysql` do Homebrew tem incompatibilidade de plugin de auth — preferir o binário do XAMPP).

## Regras de design (obrigatórias)

- **Cores da marca:** preto `#000000` e laranja `#ff8c1e`. Base de tokens em [`DESIGN.md`](../DESIGN.md) — usar
  `primary` = preto e `brand-orange` = `#ff8c1e` como accent. O accent laranja é reservado para CTAs e estados ativos.
- **Sem emojis** em nenhuma superfície do produto.
- **Ícones:** somente Font Awesome.
- **Alertas:** somente SweetAlert2.
- **Logos:** `referencia/LOGO UP.png` (fonte preta, usar em fundo claro) e
  `referencia/LOGO UP - VS 2.png` (fonte branca, usar em fundo escuro). Copiar para `public/img/` no setup.
- **Referência de layout:** `referencia/pipefy.png` — colunas do Kanban, card de detalhe, barra de abas superior,
  botão "adicionar nova coluna" ao final das colunas.
- Seguir padrões SaaS para posicionamento de botões, filtros e relacionamentos. Fluxos de criação de itens
  devem respeitar a ordem de dependência do banco (ex.: cadastrar Empresa antes de vinculá-la a um Card).

## Comandos úteis

```bash
composer install
php artisan key:generate
php artisan migrate --seed
npm install && npm run dev        # desenvolvimento (Vite)
npm run build                     # produção
php artisan serve                 # ou acessar via XAMPP em /Applications/XAMPP/xamppfiles/htdocs/upmusic
php artisan test                  # testes de backend (feature/unit) — sem testes de front/carga/e2e
```

## Convenções

- Migrations, colunas de banco e nomes de tabela em **inglês no plural** (padrão Laravel), mas rótulos de UI em **PT-BR**.
- Enums de domínio como PHP enums (`app/Domain/.../Enums`).
- Validação sempre em Form Requests; nunca validar dentro do controller.
- Regras de negócio em Actions/Services, não em controllers nem models.
- Toda listagem tem paginação, busca e filtros server-side.
- Autorização via Policies + middlewares `module:<módulo>` e `admin` (Perfis de acesso, ver [`specs/28`](../specs/28-perfis-de-acesso.md)).
  Não existem mais papéis fixos: Administrador vê tudo; os demais têm um Perfil de acesso com módulos e quadros.
- Nunca rodar `php artisan ... --env=testing`: não há `.env.testing`, então o comando cai no `upmusic_local`.
  O banco de testes (`upmusic_test`) só é usado via `php artisan test` (phpunit.xml).
- Não implementar testes de automação de front, carga, Playwright ou similares (fora de escopo).

## Modelo do Claude por fase

Convenção acordada para a implementação (trocar com `/model sonnet` ou `/model opus` ao iniciar o bloco):

- **Specs 00–05 (Fases 0–5) → `sonnet`** — setup, design, banco, auth, cadastros base.
- **Specs 06–09 (Fases 6–9) → `opus`** — quadros/config, Kanban, templates, planejamento financeiro.
- **Specs 10–13 (Fases 10–12) + CHECKLIST → `sonnet`** — banco de preços, formulário externo, fluxo, WhatsApp, refino.

## Progresso

O acompanhamento do que foi construído fica em [`specs/CHECKLIST.md`](../specs/CHECKLIST.md).
**Atualizar o checklist a cada entrega de módulo.**
