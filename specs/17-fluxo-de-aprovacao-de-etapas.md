# 17 — Fluxo de Aprovação de Etapas

> **Modelo recomendado:** `opus` (Opus 4.8) — mexe em Kanban, drag-and-drop e autorização (mesma
> complexidade de [06](06-quadros-e-departamentos.md)/[07](07-kanban-e-cards.md)).
> Melhoria pós-entrega (numeração fora das fases 0–12, como [14](14-kanban-reatividade-assincrona.md),
> [15](15-banco-de-precos-por-categoria.md) e [16](16-captura-rapida-orcamentos-nf.md)).

## 1. Objetivo

Permitir que **colunas específicas de um quadro** exijam aprovação de um administrador antes que o card
possa avançar para a próxima etapa. Se aprovado, o card segue para a próxima coluna normalmente; se
reprovado, o card é **arquivado** (reaproveitando a funcionalidade de Arquivar já existente — ver
`specs/CHECKLIST.md`, seção "Menu de ações do card"). A configuração é por quadro/coluna: nem todo quadro
precisa desse controle, e um quadro pode ter mais de uma coluna com esse gate.

## 2. Conceito

- Uma coluna (`board_columns`) pode ter **0 ou mais aprovadores** configurados. **A simples existência de
  aprovadores configurados numa coluna já a torna "coluna de aprovação"** — não existe uma flag `is_final`-like
  separada, e não existe estado "pendente" armazenado no card: um card só está "aguardando aprovação"
  enquanto estiver posicionado numa coluna que tenha aprovadores configurados. Não há necessidade de coluna
  nova em `cards` para representar esse estado.
- **Aprovador = usuário com role `admin`.** Só administradores podem ser selecionados como aprovadores e só
  administradores especificamente selecionados **para aquela coluna** podem agir — um admin que não foi
  selecionado para a coluna X não vê a opção de aprovar em cards que estejam na coluna X (ver §7, nota
  técnica importante sobre `Gate::before`).
- **Múltiplos aprovadores por coluna = qualquer um decide.** Se uma coluna tem 2+ aprovadores configurados,
  a ação do **primeiro que agir** (aprovar ou reprovar) já resolve o card — não é necessário unanimidade
  nem espera pelos demais. Os outros aprovadores configurados simplesmente deixam de ver a ação depois que
  o card já foi decidido (saiu da coluna ou foi arquivado).
- **Aprovado** → card move para a **próxima coluna do quadro por posição** (`board_columns.position`
  imediatamente maior, dentro do mesmo `board_id`).
- **Reprovado** → card é **arquivado** (`archived_at`/`archived_by`, reaproveitando `ArchiveCard`), com
  **motivo obrigatório** guardado no histórico (ver §3).

## 3. Modelo de dados

### 3.1 `board_column_approvers` (nova tabela — pivot de configuração)

| Coluna | Tipo | Regras |
|--------|------|--------|
| id | bigint PK | |
| board_column_id | bigint FK→board_columns | `cascade` |
| user_id | bigint FK→users | `cascade` |
| timestamps | | |

Único: `(board_column_id, user_id)`. A presença de qualquer linha para uma `board_column_id` marca essa
coluna como "exige aprovação". Validar na criação/edição que `user_id` referencia um usuário com
`role = admin` (Form Request, não constraint de banco — papel pode mudar depois; ver §7 sobre revalidação
em tempo de uso).

### 3.2 `cards_movements` — nova coluna `note` + dois novos `type`

| Coluna | Tipo | Regras |
|--------|------|--------|
| note | text | nullable — usado para guardar o motivo da reprovação |

Novos casos em `MovementType`: `approval` (card aprovado — comportamento de exibição igual ao `column`,
`from`/`to` = nomes das colunas) e `rejection` (card reprovado/arquivado — comportamento de exibição igual
ao `archival` já existente: `to` = null). Ambos guardam `user_id` (quem decidiu) e `created_at` (quando) —
que já é exatamente o requisito "guardar histórico de quem aprovou e qual horário foi aprovado", reaproveitando
a tabela de histórico que já existe.

Nenhuma tabela nova para o "voto" em si é necessária: como a regra é "o primeiro que agir decide" (§2), não
existe necessidade de guardar aprovações parciais de múltiplos aprovadores — o próprio `card_movements` já
é o registro definitivo da decisão.

## 4. Configuração no quadro (Admin/Coordenador)

Na tela **Configurar quadro** ([06](06-quadros-e-departamentos.md#4-crud-de-quadros)), na seção de
gerenciar colunas, cada coluna ganha um controle adicional:

- Toggle **"Exige aprovação para avançar"**.
- Ao ativar, um multi-select com os usuários de `role = admin` ativos (mesma fonte já carregada em
  `BoardController::config()` — basta filtrar por `role`), que sincroniza (`sync`) a tabela
  `board_column_approvers` para aquela coluna.
- **A última coluna do quadro (maior `position`) não pode ativar esse toggle** — não há próxima coluna
  para onde avançar em caso de aprovação. A UI oculta/desabilita o toggle nessa coluna; o backend também
  valida (ver §6 — a validação de UI não é suficiente sozinha, porque colunas podem ser reordenadas depois).
- Reordenar colunas depois de configurado: se uma coluna deixar de ter uma "próxima" (virar a última), a
  configuração de aprovadores permanece salva, mas o botão Aprovar passa a falhar com erro amigável
  ("Não há próxima etapa configurada para esta coluna.") até a configuração ser ajustada — evita apagar
  dado de configuração silenciosamente por causa de um reorder.

## 5. Fluxo no card (Aprovar/Reprovar)

No modal de detalhe do card (`card-panel.blade.php`), quando o card está posicionado numa coluna com
aprovadores configurados **e** o usuário logado é um dos aprovadores daquela coluna especificamente:

- Aparece uma **faixa de destaque no topo do card** (abaixo do cabeçalho, acima dos campos — mesmo nível
  visual de um alerta, ex.: fundo laranja/amarelo claro), com texto "Aguardando aprovação" e dois botões:
  **Aprovar** e **Reprovar**.
- Se o usuário logado NÃO é aprovador daquela coluna (inclusive se for admin, mas não estiver na lista
  configurada para esta coluna específica), a faixa não aparece — a única indicação visível para ele é um
  badge neutro "Aguardando aprovação" sem botões de ação (para avisar o time do estado do card, sem
  permitir a ação).
- **Aprovar**: confirmação simples (SweetAlert2, "Aprovar este card?"). Ao confirmar: card move para a
  próxima coluna por posição, grava `card_movements` (`type = approval`), fecha o modal (mesmo padrão de
  `doConclude()`/`doArchive()` já existentes). Atualiza o Kanban sem reload (`afterCardApproved` no
  `kanban.js`, análogo a `afterCardMoved`).
- **Reprovar**: modal com **campo de texto obrigatório** para o motivo (SweetAlert2 com `input: 'textarea'`
  ou similar). Ao confirmar: card é arquivado (mesma ação `ArchiveCard` já existente), grava
  `card_movements` (`type = rejection`, `note = <motivo>`), fecha o modal (`afterCardRejected`, análogo a
  `afterCardArchived`).
- Card arquivado por reprovação pode ser **desarquivado** normalmente pela tela "Todos os cards" (feature
  já existente) — ao desarquivar, volta para a mesma coluna de origem, reentrando automaticamente em
  "aguardando aprovação" (não é necessário nenhum estado extra para isso: a coluna continuar tendo
  aprovadores configurados já é suficiente).

## 6. Bloqueio de avanço manual (drag-and-drop e transferência)

Enquanto um card estiver numa coluna com aprovadores configurados, **avançar sem passar pela aprovação não
é permitido**:

- **Drag-and-drop** (`cards.move`): bloqueado quando o destino tem `position` **maior** que a coluna atual
  E a coluna atual exige aprovação — não só o próximo imediato, para fechar a brecha de "pular" a etapa de
  aprovação arrastando direto para uma coluna mais à frente. Mover para trás (`position` menor ou igual)
  continua liberado normalmente.
- **Enviar para outro departamento** (`cards.transfer`): também bloqueado enquanto o card estiver numa
  coluna com aprovadores configurados (é, na prática, outra forma de "avançar" o processo).
- Nos dois casos, a tentativa bloqueada retorna erro (`422`) com mensagem clara ("Este card precisa ser
  aprovado antes de avançar.") e o front reverte a posição visual (mesmo padrão de erro de rede que já
  existe em `persistMove()`).
- Edição de campos, comentários e anexos continuam liberados normalmente — o bloqueio é só sobre avançar
  de etapa/departamento.

## 7. Autorização — nota técnica importante

`AuthServiceProvider` registra `Gate::before(fn (User $user) => $user->isAdmin() ? true : null)` — **todo
admin passa automaticamente em qualquer Policy**, inclusive uma futura `CardPolicy::approve()`. Isso
**quebraria o requisito central** desta spec ("só quem foi selecionado como aprovador daquela coluna pode
aprovar", não qualquer admin do sistema).

**Por isso, a checagem de permissão de aprovar/reprovar NÃO deve passar pelo sistema de Policy/Gate padrão**
(`$this->authorize(...)`). Deve ser uma checagem explícita no controller/Action, por exemplo:

```php
abort_unless(
    $card->column->approvers->contains(fn ($u) => $u->id === $request->user()->id && $u->isAdmin()),
    403
);
```

Revalidar `role === admin` no momento da ação (não confiar só na existência da linha em
`board_column_approvers`) cobre o caso de um usuário ter sido rebaixado de admin depois de configurado
como aprovador — a linha continua no pivot, mas ele perde a permissão de fato até ser promovido de novo ou
removido/adicionado novamente pela configuração.

## 8. Reprovação = arquivamento (reaproveitamento)

A reprovação não precisa de nenhuma tabela ou coluna nova de estado — reaproveita 100% a feature de
Arquivar já implementada nesta mesma sessão (`archived_at`/`archived_by` em `cards`, Action `ArchiveCard`,
filtro em `BoardController::kanbanData()`, badge/filtro "Arquivado" em "Todos os cards"). A única adição é
o motivo (`card_movements.note`) e o novo `MovementType::Rejection` para diferenciar no histórico "arquivado
por reprovação de etapa" de "arquivado manualmente pelo usuário" (ambos usam os mesmos campos
`archived_at`/`archived_by`, mas o tipo de movimentação registrado é diferente).

## 9. Rotas

```
PUT    /colunas/{column}/aprovadores   columns.approvers.update   role:admin,coordenador (sync user_ids[])
POST   /cards/{card}/aprovar           cards.approve               checagem manual (ver §7)
POST   /cards/{card}/reprovar          cards.reject                checagem manual (ver §7) — body: { reason }
```

## 10. Suposições assumidas (confirmar se algo estiver errado)

Perguntas centrais já foram validadas com o usuário (decisão "qualquer um decide", bloqueio de drag-and-drop,
motivo obrigatório na reprovação, banner no topo do card). As suposições abaixo **não** passaram por
confirmação explícita — são o caminho mais simples/consistente com o que já existe no projeto, mas devem
ser revistas se não for o esperado:

- **"Próxima coluna" é sempre a próxima por `position` dentro do mesmo quadro** — não existe seleção de
  uma coluna de destino específica na configuração (diferente do fluxo de "enviar para outro departamento",
  que pergunta o destino). Se o fluxo real precisar pular colunas ou ter destino configurável, a
  configuração da coluna precisaria de um campo extra (`target_column_id`).
- **Transferência para outro departamento também é bloqueada** enquanto pendente de aprovação (§6) — o
  pedido original não menciona esse caso explicitamente; assumi que "avançar" inclui transferência entre
  quadros, já que ela também move o processo para frente.
- **Notificação aos aprovadores** (e-mail, painel, etc.) quando um card entra numa coluna que exige
  aprovação está **fora de escopo** desta entrega — o projeto não tem infraestrutura de notificação hoje
  (fora o card "Vencendo hoje" do dashboard, que é uma consulta, não um envio ativo). Aprovadores precisam
  checar o quadro/"Todos os cards" para ver cards aguardando.
- **Sem prazo/SLA de aprovação** — não há alerta de "aprovação atrasada" nesta versão.

## 11. Critérios de aceite

- [x] Configuração de coluna permite marcar "Exige aprovação" e selecionar 1+ aprovadores (só usuários
      `role = admin`); não permite ativar na última coluna do quadro.
- [x] Card numa coluna com aprovadores configurados mostra faixa "Aguardando aprovação" com botões
      Aprovar/Reprovar **somente** para os aprovadores daquela coluna especificamente — outros usuários
      (inclusive outros admins não selecionados) não veem os botões.
- [x] Aprovar move o card para a próxima coluna por posição e grava `card_movements` (`type = approval`,
      usuário e horário corretos).
- [x] Reprovar exige motivo, arquiva o card (`archived_at`/`archived_by`) e grava `card_movements`
      (`type = rejection`, motivo em `note`, usuário e horário corretos).
- [x] Drag-and-drop para uma coluna à frente é bloqueado enquanto o card está numa coluna com aprovação
      pendente; mover para trás continua livre.
- [x] "Enviar para outro departamento" bloqueado nas mesmas condições.
- [x] Checagem de permissão de aprovar/reprovar **não** usa o `Gate::before` de admin — é validação manual
      contra `board_column_approvers` (ver §7).
- [x] Card arquivado por reprovação pode ser desarquivado (feature já existente) e volta a aguardar
      aprovação na mesma coluna (mecanismo já existente reaproveitado; não houve necessidade de teste
      HTTP adicional específico para este item, já coberto pela validação da feature de Arquivar/Desarquivar).
