# 08 — Templates de Cards

> **Modelo recomendado:** `opus` (Opus 4.8) — módulo de maior complexidade.

## 1. Objetivo

Agilizar a criação repetitiva de cards. Ao iniciar um evento, por exemplo, é comum precisar de vários
cards de orçamento (limpeza, segurança, som, etc.). Em vez de criá-los manualmente toda vez, o usuário
cadastra um template com um conjunto pré-definido de cards e o importa para o quadro, gerando os cards
automaticamente.

## 2. Cadastro de templates

- **Template** (`card_templates`): nome, descrição, quadro-alvo sugerido (opcional), ativo.
- **Itens do template** (`card_template_items`): cada item vira um card. Campos: título, descrição,
  coluna de destino padrão (opcional), valores pré-preenchidos de campos (`default_fields` em JSON), posição.
- Tela de edição do template lista os itens com reordenação (drag) e botão "adicionar item".
- **Acesso:** Admin e Coordenador criam/editam; qualquer perfil com acesso ao quadro pode importar.

## 3. Importar template para o quadro

- Ação "Importar template" no Kanban (topbar ou botão de criação).
- Fluxo: escolher o template → confirmar quadro/coluna de destino → (opcional) vincular uma **empresa**
  a todos os cards gerados → confirmar.
- Ao confirmar, para cada item do template é criado um card no quadro com `origin = template`, na coluna
  de destino (item ou padrão do quadro), aplicando `default_fields` aos `card_field_values` compatíveis.
- Operação em **transação**; feedback SweetAlert2 com o total de cards criados.

## 4. Regras de negócio

- Só importar itens cujos campos/colunas existam no quadro de destino; incompatibilidades são ignoradas
  com aviso (log/toast), sem quebrar a importação.
- Vincular empresa na importação preenche `empresa_id` de todos os cards gerados.
- Template inativo não aparece para importação.

## 5. Rotas (web)

```
Resource /templates            templates.*            role:admin,coordenador
POST     /templates/{template}/itens        template.items.store
PUT      /template-itens/{item}             template.items.update
DELETE   /template-itens/{item}             template.items.destroy
POST     /templates/{template}/itens/reordenar  template.items.reorder
POST     /quadros/{board}/importar-template templates.import   (gera os cards)
```

## 6. Critérios de aceite

- [ ] CRUD de templates com itens reordenáveis.
- [ ] Importação gera múltiplos cards no quadro/coluna corretos em uma transação.
- [ ] Vínculo opcional de empresa aplicado a todos os cards gerados.
- [ ] Cards gerados marcados com `origin = template` e com `default_fields` aplicados.
- [ ] Incompatibilidades tratadas sem quebrar a importação.
