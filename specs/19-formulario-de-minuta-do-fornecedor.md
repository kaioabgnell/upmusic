# 19 — Formulário de Minuta do Fornecedor (link por card)

> **Modelo recomendado:** `opus` (Opus 4.8) — integra o modal do card do Kanban a um formulário público
> por card, com nova tabela e upload externo. Melhoria pós-entrega (numeração fora das fases 0–12, como
> [14](14-kanban-reatividade-assincrona.md)/[15](15-banco-de-precos-por-categoria.md)/
> [16](16-captura-rapida-orcamentos-nf.md)/[17](17-fluxo-de-aprovacao-de-etapas.md)/
> [18](18-link-direto-e-compartilhamento-de-card.md)).

## 1. Objetivo

Depois que um orçamento é aprovado e o card chega ao departamento Jurídico (via transferência entre
quadros — ver [07](07-kanban-e-cards.md#8-transição-entre-departamentos-envio-para-outro-quadro)), a
equipe precisa **acionar o fornecedor aprovado para que ele mesmo envie a minuta do contrato**. Em vez de
o Jurídico redigir um contrato do zero, o fornecedor recebe um **link único daquele card**, abre uma
página pública onde vê os dados do orçamento aprovado (empresa, valor, descrição) e **anexa a própria
minuta**, que cai direto **dentro do card** para o Jurídico analisar só as cláusulas essenciais.

Isso é o inverso do [Formulário Externo (spec 11)](11-formulario-externo.md): lá o envio **cria um card
novo**; aqui o envio **anexa um arquivo a um card já existente**, e o link carrega o contexto daquele card
específico.

## 2. Conceito

- **Habilitação por quadro** (decisão confirmada): em **Configurar quadro** há um toggle
  **"Permitir solicitar minuta ao fornecedor"** (`boards.allows_supplier_form`). Só quando ligado é que os
  cards daquele quadro ganham a ação de gerar o link. Tipicamente ligado no quadro **Jurídico**.
- **Link único por card**: cada card gera (sob demanda) um `card_supplier_forms` com um `token` aleatório.
  A URL pública é `GET /minuta/{token}` — sem autenticação, identidade visual Up Music
  (`x-public-layout`, mesmo do formulário externo).
- **Página do fornecedor (somente leitura + upload)**: mostra os **dados da empresa/cliente** (razão
  social, CNPJ), o **valor aprovado** do orçamento, o **evento** e a **descrição do serviço** do card —
  para o fornecedor comparar com o que está em contrato. Abaixo, um formulário para **anexar o arquivo da
  minuta** (PDF/DOC/DOCX) e uma **observação opcional**.
- **Minuta cai no card**: ao enviar, o arquivo vira um `card_attachment` do card (novo `kind = minuta`),
  e um registro `card_supplier_submissions` guarda a observação, IP e data para auditoria. O Jurídico
  encontra a minuta na aba de Anexos do card, como qualquer outro anexo.
- **Link reutilizável** (decisão confirmada): o mesmo link aceita **vários envios** (ex.: uma minuta e
  depois uma revisão) — cada envio é um novo anexo + um novo submission. A equipe pode **desativar** o
  link a qualquer momento (`card_supplier_forms.active = false`), o que invalida a URL sem apagar nada.
- **Sem notificação ativa** (fora de escopo, consistente com specs 16/17): o projeto não tem
  infraestrutura de e-mail/push. A equipe vê a minuta ao abrir o card; a entrega do link ao fornecedor é
  feita **copiando o link** (área de transferência) e enviando por fora (WhatsApp/e-mail do próprio
  operador), mesmo padrão de "Compartilhar formulário" e "Compartilhar Card" (specs/18).

## 3. Modelo de dados

### 3.1 `boards` — nova coluna

| Coluna | Tipo | Regras |
|--------|------|--------|
| allows_supplier_form | boolean | default `false` — habilita a solicitação de minuta nos cards do quadro |

### 3.2 `card_supplier_forms` (nova tabela — o link por card)

| Coluna | Tipo | Regras |
|--------|------|--------|
| id | bigint PK | |
| card_id | bigint FK→cards | `cascade`, **único** (um link por card; regerar troca o token no mesmo registro) |
| token | varchar(40) | único, aleatório (não sequencial), usado na URL pública |
| active | boolean | default `true` — desativar invalida o link sem apagar |
| created_by | bigint FK→users | `set null`, nullable — quem gerou o link |
| timestamps | | |

### 3.3 `card_supplier_submissions` (nova tabela — cada minuta recebida)

| Coluna | Tipo | Regras |
|--------|------|--------|
| id | bigint PK | |
| card_supplier_form_id | bigint FK→card_supplier_forms | `cascade` |
| card_id | bigint FK→cards | `cascade` (redundância proposital p/ consulta direta) |
| card_attachment_id | bigint FK→card_attachments | `set null`, nullable — a minuta anexada |
| note | text | nullable — observação opcional do fornecedor |
| ip | varchar(45) | nullable |
| timestamps | | |

### 3.4 `AttachmentKind` — novo caso

`minuta` (label "Minuta"), somando-se a `geral`/`nota_fiscal`/`comprovante`/`orcamento`.

## 4. Backend

### 4.1 Configuração do quadro

`boards/config.blade.php` ganha, junto do toggle de acesso, o switch **"Permitir solicitar minuta ao
fornecedor"** (`allows_supplier_form`), salvo por `BoardController::update()`/rota de config (Admin/
Coordenador, `authorize('configure', $board)`). Nenhuma regra nova de permissão.

### 4.2 Gerar / gerenciar o link (equipe, autenticado)

Ação disparada de dentro do card (ver §5.1). Um `CardSupplierFormController` (web, autenticado) com:

```
POST   /cards/{card}/minuta/link        supplier.link.generate   (gera/reativa o link; authorize('update', $card))
DELETE /cards/{card}/minuta/link        supplier.link.disable     (active = false)
```

- `generate`: `abort_unless($card->board->allows_supplier_form, 422)`; `firstOrCreate` do
  `card_supplier_forms` para o card, gerando `token` se novo, e `active = true` (reativa se estava
  desativado). Retorna a URL pública pronta para copiar.
- `disable`: marca `active = false`. Nada é apagado.

### 4.3 Página pública do fornecedor (sem auth)

`SupplierFormController` em `routes/web.php` no topo (bloco público, junto de `/f/{token}`):

```
GET  /minuta/{token}           supplier.form.show      (route model binding por token)
POST /minuta/{token}           supplier.form.submit    throttle:10,1
GET  /minuta/{token}/sucesso   supplier.form.success
```

- `show`: `abort_unless($form->active, 404)`; carrega o card com empresa/evento; renderiza a página
  read-only + formulário de upload. Nunca expõe dados internos além dos listados em §2.
- `submit` (transação, espelha `ProcessExternalSubmission`):
  1. Valida upload (MIME `pdf,doc,docx`, tamanho máx. ex. 10 MB) e `note` opcional.
  2. Armazena o arquivo (`storage/app` privado, ex. `supplier-minutas/{card_id}`).
  3. Cria `card_attachments` no card (`kind = minuta`, `uploaded_by = null`).
  4. Cria `card_supplier_submissions` (form, card, attachment, note, ip).
  5. Registra `card_movements` (novo tipo? — ver §6, suposição) ou apenas deixa o anexo falar por si.
  6. Redireciona para a tela de sucesso.

### 4.4 Autorização

- Gerar/desativar link: `authorize('update', $card)` (mesmo padrão de concluir/transferir/arquivar).
- Página pública: sem auth, gated pelo `token` + `active` (igual ao formulário externo da spec 11).

## 5. Frontend

### 5.1 Modal do card (`card-panel.blade.php` + `card-panel.js`)

Nova **seção "Formulário do fornecedor (minuta)"** no corpo do card, visível só quando
`mode === 'view' && cardId` **e** o quadro permite (`cfg` ou o JSON do card traz
`board_allows_supplier_form`). Comportamento:

- Se ainda não há link: botão **"Gerar link para o fornecedor"**.
- Se há link ativo: mostra a URL, um botão **"Copiar link"** (`navigator.clipboard.writeText` +
  `notifySuccess('Link copiado para sua área de transferência.')`, mesmo padrão da spec 18) e um botão
  **"Desativar link"**.
- Lista as **minutas recebidas** (contagem + link para o anexo), já que os arquivos também aparecem na
  seção de Anexos.
- Reaproveita o `card-panel.js` compartilhado; como a seção depende de dado por-card, o Kanban e a
  listagem global ("Todos os cards") funcionam igual.

Colocação: como bloco no corpo do card (à altura de "Enviar para outro departamento"/"Anexos"), **não** no
menu de 3 pontos — é uma seção com estado (link + lista), não uma ação pontual.

### 5.2 Página pública (`resources/views/supplier/*.blade.php`)

`x-public-layout` (logo Up Music, marca), responsiva, Font Awesome, SweetAlert2 no feedback. Blocos:

1. **Resumo do orçamento aprovado** (read-only): empresa (razão social + CNPJ), evento, **valor
   aprovado** (BRL), descrição do serviço.
2. **Formulário**: upload da minuta (obrigatório) + observação (opcional) + botão Enviar.
3. **Tela de sucesso** após envio, com opção de enviar outra minuta (link reutilizável).

## 6. Regras de negócio / casos de borda

- Quadro sem `allows_supplier_form` → a seção não aparece e `generate` retorna `422`.
- `token` de link desativado (`active = false`) → `404` na página pública.
- Card excluído (soft delete) → `cascade` remove o form/submissions; a URL vira `404`.
- Reaproveitamento: cada envio adiciona um novo anexo `minuta` e um novo submission — **nada é
  sobrescrito nem apagado**.
- A minuta é sempre privada no storage; download só pelo card (rota autenticada de anexos já existente,
  `cards.attachments.download`).
- Validação estrita de upload e `throttle` na rota pública, iguais à spec 11.

## 7. Suposições assumidas (confirmar se algo estiver errado)

As 4 decisões centrais foram confirmadas com o usuário (habilitação por quadro; exibir empresa + valor
aprovado + descrição; envio = arquivo + observação; link reutilizável). As abaixo **não** foram
confirmadas e seguem o caminho mais consistente com o projeto:

- **"Valor aprovado" exibido = `estimated_value`** do card (o valor do orçamento). Se o valor a comparar
  for o `actual_value` (realizado) ou outro, ajustar.
- **Entrega do link por cópia** (área de transferência), não por e-mail — o projeto não tem envio de
  e-mail configurado. Se for necessário disparar e-mail ao fornecedor, é um item à parte (infra de mail).
- **Registro no histórico do card** (`card_movements`) ao receber minuta: proposto como opcional/leve
  (ou nenhum) — a minuta já aparece nos Anexos. Adicionar um tipo `minuta_recebida` ao `MovementType` é
  possível, mas não foi pedido; deixado fora por padrão para não inflar o enum.
- **Sem notificação ativa** ao Jurídico quando a minuta chega (fora de escopo, sem infra).
- **Um link por card** (regerar troca o token do mesmo registro). Se precisar de vários links simultâneos
  por card (ex.: dois fornecedores no mesmo card), o modelo mudaria para permitir N forms por card.

## 8. Rotas

```
# Público (sem auth)
GET  /minuta/{token}            supplier.form.show
POST /minuta/{token}            supplier.form.submit     throttle:10,1
GET  /minuta/{token}/sucesso    supplier.form.success

# Equipe (web, autenticado — authorize('update', $card))
POST   /cards/{card}/minuta/link   supplier.link.generate
DELETE /cards/{card}/minuta/link   supplier.link.disable
```

## 9. Critérios de aceite

- [x] Toggle "Permitir solicitar minuta ao fornecedor" em Configurar quadro (`allows_supplier_form`).
- [x] Card de quadro habilitado mostra a seção "Formulário do fornecedor (minuta)" com gerar/copiar/
      desativar link; quadro não habilitado não mostra a seção e `generate` retorna `422`.
- [x] Página pública `/minuta/{token}` exibe empresa + valor aprovado + evento + descrição (read-only) e
      o formulário de upload da minuta + observação, com identidade Up Music.
- [x] Envio anexa a minuta ao card (`kind = minuta`, arquivo privado) e registra o submission (note, ip).
- [x] Link reutilizável: um segundo envio gera um segundo anexo/submission, sem sobrescrever o primeiro.
- [x] Desativar o link (`active = false`) faz a URL retornar `404`, sem apagar dados.
- [x] Gerar/desativar link exige acesso ao card (`authorize('update', $card)`); página pública é
      token-gated sem auth; `throttle` e validação de upload aplicados.

> **Nota sobre validação**: os fluxos acima foram confirmados por HTTP real (sessão de admin + POST
> público multipart), sem Playwright/teste visual e sem apagar nenhum registro, a pedido do usuário. O
> toggle do quadro usado no teste foi restaurado ao estado original; os anexos/submissions de teste
> criados no card usado permaneceram no banco (não removidos, conforme a instrução de não apagar
> registros).
