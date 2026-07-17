# 07 — Kanban de Processos e Cards

> **Modelo recomendado:** `opus` (Opus 4.8) — módulo de maior complexidade.

Referência de experiência: Pipefy (`referencia/pipefy.png`). Ver design em [02](02-design-system.md).

## 1. Objetivo

Quadro visual com colunas representando as etapas do processo, permitindo mover cards conforme o
andamento; criação e detalhamento de cards estilo Pipefy; vínculo de empresa; filtro por empresa;
e transição de cards entre departamentos/quadros.

## 2. Tela do Kanban

- **Topbar do quadro:** ícone + nome do quadro/departamento; à direita: busca de cards, filtro (empresa,
  responsável, prioridade), "Compartilhar formulário" (link externo — [11](11-formulario-externo.md)) e "Configurar" (Admin/Coord).
- **Abas** (estilo Pipefy, adaptadas ao escopo): **Kanban** (padrão) e **Lista**. (Mapa/Relatórios opcionais/futuros.)
- **Colunas** de largura fixa, com cabeçalho: nome da etapa + contador de cards. Cards empilhados verticalmente.
- **"Adicionar nova coluna"** sempre ao final das colunas (abre criação inline de coluna — Admin/Coord).
- **"+ Criar novo card"** no rodapé/topo da coluna de entrada, estilo Pipefy.
- Scroll horizontal quando as colunas excedem a largura; responsivo (mobile: colunas com scroll).

## 3. Card compacto (na coluna)

Exibe: título, empresa vinculada (badge), responsável (avatar/nome), prazo, valor previsto e/ou
indicadores (prioridade, nº de anexos/comentários). Ícones Font Awesome. Clique abre o detalhe.

## 4. Card de detalhe (painel/modal estilo Pipefy)

Formulário completo de detalhamento:

- **Campos fixos:** título, descrição, empresa (select com busca + atalho cadastrar inline), responsável,
  prazo, prioridade, valor previsto, valor realizado.
- **Campos configuráveis do quadro** (board_fields): renderizados por tipo (text/textarea/number/money/
  date/select/checkbox/email/phone/file), respeitando obrigatoriedade.
- **Anexos** (card_attachments): upload de arquivos, incluindo notas fiscais e fotos de comprovação
  (campo `kind`). Preview/download; excluir com confirmação.
- **Comentários** (card_comments): thread com autor e data.
- **Histórico** (card_movements): timeline de mudanças de coluna/quadro.
- Ações: salvar, mover de coluna, **enviar para outro departamento** (se na coluna final), excluir.

## 5. Criação de card

- Fluxo respeita dependências: a **empresa** deve existir para ser vinculada (atalho de cadastro inline via modal).
- Card criado entra na coluna de entrada do quadro (ou coluna escolhida), com `origin = manual`.
- Validação via Form Request (título obrigatório; campos obrigatórios do quadro).
- Feedback SweetAlert2 (toast de sucesso).

## 6. Mover card (drag-and-drop)

- SortableJS entre colunas e reordenação dentro da coluna.
- Ao soltar: chamada AJAX persiste `board_column_id` e `position` (reordenação em lote na transação) e
  grava `card_movements` (type `column`).
- Falha de rede reverte visualmente e mostra erro (SweetAlert2).

## 7. Vínculo de empresa e filtro

- Empresa vinculada via select com busca (a partir do cadastro de [Empresas](05-cadastros-base.md)).
- **Filtro por empresa** no topo do quadro (além de responsável/prioridade/busca textual), aplicado server-side.

## 8. Transição entre departamentos (envio para outro quadro)

Requisito central: quando o card chega à **última coluna** (`is_final`) do quadro, aparece um botão
**"Enviar para outro departamento"**.

- Ao acionar: modal (SweetAlert2 ou painel) para escolher o **quadro de destino** e a **coluna de destino**
  (default: coluna de entrada do destino).
- Confirmação: o card passa a pertencer ao quadro destino (`board_id`/`board_column_id` atualizados),
  registra `card_movements` (type `board`, from/to board e coluna) e **passa a aparecer no quadro do outro
  departamento**.
- Preserva histórico, anexos, comentários e valores. Campos configuráveis específicos do quadro de origem
  que não existam no destino ficam retidos no histórico (não são exibidos no destino).
- Notificação opcional ao responsável do destino (fase futura).

## 9. Endpoints (JSON/AJAX)

```
GET    /quadros/{board}/kanban            boards.kanban.data   (colunas + cards, com filtros)
POST   /quadros/{board}/cards             cards.store
GET    /cards/{card}                      cards.show           (detalhe JSON)
PUT    /cards/{card}                      cards.update
DELETE /cards/{card}                      cards.destroy
POST   /cards/{card}/mover                cards.move           (coluna + posição)
POST   /cards/{card}/enviar-departamento  cards.transfer       (board/coluna destino)
POST   /cards/{card}/comentarios          cards.comments.store
POST   /cards/{card}/anexos               cards.attachments.store
DELETE /anexos/{attachment}               cards.attachments.destroy
```

## 10. Critérios de aceite

- [ ] Kanban com colunas configuráveis, contador por coluna e botão "adicionar nova coluna" ao final.
- [ ] Criar card com formulário de detalhe estilo Pipefy (campos fixos + configuráveis).
- [ ] Drag-and-drop persistindo coluna/posição e gravando histórico, sem N+1.
- [ ] Vínculo de empresa (com cadastro inline) e filtro por empresa no quadro.
- [ ] Anexos (inclui NF e fotos de comprovação), comentários e histórico no card.
- [ ] Botão "enviar para outro departamento" na coluna final, movendo o card ao quadro destino.
- [ ] Card transferido aparece no quadro de destino com histórico preservado.
