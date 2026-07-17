# 04 — Autenticação e Controle de Acesso

> **Modelo recomendado:** `sonnet` (Sonnet 5).

## 1. Objetivo

Controlar o acesso ao sistema com login, gestão de usuários e três níveis de permissão
(Admin, Coordenador, Usuário).

## 2. Autenticação

- Base: Laravel Breeze (stack Blade), re-estilizado ao design da marca (ver [02](02-design-system.md)).
- **Tela de login:** fundo com identidade Up Music, logo apropriada ao contraste, campos e-mail + senha,
  "lembrar-me", link "esqueci minha senha".
- Recuperação de senha por e-mail (fluxo padrão do Breeze).
- Sem cadastro público (registro aberto desativado) — usuários são criados por Admin/Coordenador.
- Usuário com `active = false` não consegue autenticar (checagem no login + middleware).
- Sessão com timeout configurável; logout limpa a sessão.

## 3. Gestão de usuários (CRUD)

Somente **Admin** (e Coordenador com escopo limitado) acessa `Usuários`.

- Listagem paginada: nome, e-mail, perfil, setor, status; busca por nome/e-mail; filtro por perfil/setor.
- Criar/editar: nome, e-mail, perfil (role), setor, telefone, senha (definir/redefinir), status ativo,
  e (para perfil `usuario`) seleção dos **quadros com acesso** (pivot `user_board`).
- Excluir = soft delete; não permitir excluir o próprio usuário nem o último admin.
- Ações destrutivas com confirmação SweetAlert2.

## 4. Níveis de permissão

| Capacidade | Admin | Coordenador | Usuário |
|-----------|:-----:|:-----------:|:-------:|
| Configuração do sistema / integrações | ✔ | — | — |
| Gerenciar usuários | ✔ | parcial¹ | — |
| Criar/editar quadros e colunas | ✔ | ✔ | — |
| Criar/editar/mover cards | ✔ | ✔ | ✔² |
| Transição de card entre departamentos | ✔ | ✔ | ✔² |
| Cadastros base (setores/empresas/fornecedores) | ✔ | ✔ | ver³ |
| Templates de cards | ✔ | ✔ | usar |
| Planejamento financeiro | ✔ | ✔ | ver³ |
| Banco de preços | ✔ | ✔ | ver³ |
| Formulário externo (config) | ✔ | ✔ | — |

¹ Coordenador gerencia usuários `usuario` da sua equipe/quadros, não cria Admin.
² Usuário só nos quadros a que tem acesso (`user_board`).
³ "ver": leitura conforme configuração; escrita opcional por quadro/módulo. Ajustável na implementação.

## 5. Implementação

- Enum `App\Domain\Enums\UserRole` (`admin`, `coordenador`, `usuario`).
- Middleware `EnsureRole` (`role:admin`, `role:admin,coordenador`) nas rotas.
- **Policies** por entidade (`BoardPolicy`, `CardPolicy`, `UserPolicy`, `EmpresaPolicy`, ...) com
  `Gate::before` liberando Admin.
- Helpers no model User: `isAdmin()`, `isCoordenador()`, `canAccessBoard(Board)`.
- `usuario` só enxerga no menu/Kanban os quadros do seu `user_board`.

## 6. Rotas (web, autenticadas)

```
GET  /login, POST /login, POST /logout            (Breeze)
GET  /forgot-password ... (Breeze)
GET  /usuarios                 users.index        role:admin[,coordenador]
GET  /usuarios/criar           users.create
POST /usuarios                 users.store
GET  /usuarios/{user}/editar   users.edit
PUT  /usuarios/{user}          users.update
DELETE /usuarios/{user}        users.destroy
```

## 7. Critérios de aceite

- [ ] Login/logout e recuperação de senha funcionando com o design da marca.
- [ ] Usuário inativo não autentica.
- [ ] CRUD de usuários com os 3 perfis e vínculo de quadros para `usuario`.
- [ ] Policies e middleware barram acesso indevido (testado por perfil).
- [ ] `usuario` só vê seus quadros no menu e no Kanban.
- [ ] Não é possível excluir o último admin nem a si mesmo.
