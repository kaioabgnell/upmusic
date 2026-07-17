# 06 — Quadros e Departamentos

> **Modelo recomendado:** `opus` (Opus 4.8) — módulo de maior complexidade.

## 1. Objetivo

Cadastro de quadros (estilo Trello/Pipefy), onde **cada quadro representa um departamento**. Um menu
de "Quadros / Processos" dá acesso aos quadros; dentro de cada quadro existe o fluxo de processo (Kanban).

## 2. Conceito

- **Quadro (board) = Departamento.** O quadro referencia um **Setor** ([05](05-cadastros-base.md)) que
  identifica o departamento (Orçamentos, Jurídico, Financeiro, Conclusão).
- Cada quadro tem suas **colunas/etapas** (fluxo de processo) e **campos de card** configuráveis.
- Cards percorrem as colunas e, na última coluna, podem ser **enviados para outro quadro/departamento**
  (ver [07](07-kanban-e-cards.md)).

## 3. Menu de navegação

Sidebar com seção **"Quadros"** listando os quadros/departamentos (ícone + nome + cor), ordenados por
`position`. Para perfil `usuario`, mostra só os quadros do seu `user_board`. Item ativo destacado em laranja.
Abaixo, seção **"Cadastros"**, **"Financeiro"**, **"Banco de Preços"**, **"Usuários"**.

## 4. CRUD de quadros

- **Campos:** nome (obrigatório), setor/departamento (select), descrição, cor, ícone (FA), posição, ativo.
- **Listagem/Gestão de quadros:** grade ou lista de quadros com nome, setor, nº de colunas, nº de cards, status.
- **Criar quadro:** ao salvar, opção de iniciar com colunas padrão do setor (do seed/fluxo) ou vazio.
- **Configurar quadro** (tela dedicada, Admin/Coordenador):
  - Gerenciar **colunas** (board_columns): adicionar, renomear, reordenar (drag), definir cor, marcar
    `is_final` (habilita envio para outro quadro) e `is_entry` (recebe cards do formulário externo).
    Botão **"Adicionar nova coluna"** sempre ao final das colunas.
  - Gerenciar **campos do card** (board_fields): adicionar/editar/reordenar campos (tipos em [03](03-modelo-de-dados.md)),
    marcar obrigatórios, definir opções de select.
  - Gerenciar **acesso** (user_board) dos usuários ao quadro.
- **Excluir quadro:** soft delete, com confirmação; alerta se houver cards ativos.

## 5. Regras de negócio

- Todo quadro deve ter ao menos uma coluna antes de aceitar cards.
- Ao menos uma coluna marcada como final é recomendada para habilitar transição entre departamentos.
- Reordenação de colunas persiste `position` em lote (transação).
- Cor/ícone do quadro herdados do setor por padrão, editáveis.

## 6. Rotas (web)

```
GET    /quadros                 boards.index      (menu/lista)
GET    /quadros/criar           boards.create     role:admin,coordenador
POST   /quadros                 boards.store
GET    /quadros/{board}         boards.show       (abre o Kanban — ver 07)
GET    /quadros/{board}/config  boards.config     role:admin,coordenador
PUT    /quadros/{board}         boards.update
DELETE /quadros/{board}         boards.destroy

# Colunas
POST   /quadros/{board}/colunas             columns.store
PUT    /colunas/{column}                    columns.update
DELETE /colunas/{column}                    columns.destroy
POST   /quadros/{board}/colunas/reordenar   columns.reorder

# Campos do card
POST   /quadros/{board}/campos              fields.store
PUT    /campos/{field}                      fields.update
DELETE /campos/{field}                      fields.destroy
POST   /quadros/{board}/campos/reordenar    fields.reorder

# Acesso
PUT    /quadros/{board}/acesso              boards.access.update
```

## 7. Critérios de aceite

- [ ] Menu lista quadros/departamentos respeitando o acesso do perfil.
- [ ] CRUD de quadros com vínculo ao setor.
- [ ] Configuração de colunas com reordenação drag, cor e flags `is_final`/`is_entry`.
- [ ] Botão "Adicionar nova coluna" ao final, conforme Pipefy.
- [ ] Configuração de campos de card por quadro.
- [ ] Gestão de acesso de usuários por quadro.
