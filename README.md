# upMusic — Sistema de Gestão de Processos Internos

Plataforma web (Laravel + MySQL) que substitui o controle em Excel da Up Music por um
sistema centralizado e colaborativo. O núcleo é um quadro Kanban de processos inspirado
no Pipefy, onde cada quadro representa um departamento e os cards percorrem etapas
configuráveis, podendo transitar entre departamentos. Complementam o núcleo: cadastros
base, templates de cards, planejamento financeiro (previsto x realizado), banco de
preços histórico e um formulário externo público para clientes.

Consulte as especificações completas em [`specs/README.md`](specs/README.md) e o
acompanhamento de entregas em [`specs/CHECKLIST.md`](specs/CHECKLIST.md).

## Stack

- **Backend:** PHP 8.1 + Laravel 10
- **Banco:** MySQL
- **Frontend:** Blade + Tailwind CSS + Alpine.js + Vite, drag-and-drop com SortableJS
- **Ícones:** Font Awesome · **Alertas:** SweetAlert2

## Requisitos

- PHP 8.1+ com as extensões usuais do Laravel (mbstring, pdo_mysql, fileinfo, gd)
- Composer 2
- Node.js 18+ e npm
- MySQL 8 (ou MariaDB compatível)

## Instalação (ambiente local)

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configurar o banco no `.env` (o banco precisa já existir — as tabelas são criadas só
via migrations):

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=upmusic_local
DB_USERNAME=root
DB_PASSWORD=
```

```bash
php artisan migrate --seed
npm install && npm run dev    # desenvolvimento (Vite com hot reload)
php artisan serve             # http://127.0.0.1:8000
```

### Usuários de teste (seed)

Senha `password` para todos:

| E-mail | Perfil |
|---|---|
| `admin@upmusic.local` | Admin |
| `coordenador@upmusic.local` | Coordenador |
| `usuario@upmusic.local` | Usuário (acesso ao quadro Orçamentos) |

## Deploy / produção

```bash
composer install --no-dev --optimize-autoloader
npm install && npm run build      # gera resources em public/build (Vite)
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Pontos de atenção:

- Definir `APP_ENV=production` e `APP_DEBUG=false` no `.env` de produção.
- Configurar `APP_URL` corretamente (usado nos links de anexo e no link do formulário
  externo `/f/{token}`).
- O disco de anexos de card usa o disk `local` (`storage/app`) — garantir que
  `storage/` e `bootstrap/cache/` tenham permissão de escrita para o usuário do PHP-FPM.
- Rodar `php artisan storage:link` — **obrigatório**: a foto de perfil do usuário usa o
  disk `public` (`storage/app/public/avatars`) e é servida diretamente via `public/storage`.
  Os demais anexos de card continuam no disk `local`, com download sempre autenticado via rota.
- Configurar um cron/scheduler (`php artisan schedule:run` a cada minuto) se alguma
  tarefa agendada for adicionada no futuro (nenhuma existe no MVP).

## Comandos úteis

```bash
php artisan test        # testes de backend (feature/unit)
./vendor/bin/pint        # padronização de estilo de código (PSR-12 + regras Laravel)
php artisan route:list   # inspecionar rotas registradas
```

Fora de escopo do projeto (ver [`specs/00-visao-geral.md`](specs/00-visao-geral.md)):
testes de front-end, carga ou E2E (Playwright ou similares).

## Arquitetura

Arquitetura limpa/em camadas — ver [`specs/01-arquitetura-tecnica.md`](specs/01-arquitetura-tecnica.md):

- `app/Actions` — regras de negócio (uma ação por caso de uso, transacional quando grava em mais de uma tabela)
- `app/Domain/Enums` — enums de domínio (papéis, tipos, status)
- `app/Services` — leitura/agregação (relatórios, comparativos, séries históricas)
- `app/Policies` + middleware de role — autorização por perfil (`admin`, `coordenador`, `usuario`)
- Controllers finos: validam via Form Requests, autorizam via Policies, delegam a Actions/Services
