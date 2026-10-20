# 24 — Gestão de Locações (inventário + pedidos + financeiro próprio)

> **Modelo recomendado:** `opus` (Opus 5.5) — módulo novo com Kanban próprio, máquina de estados com
> gates, cálculo de disponibilidade por período e financeiro (previsto x realizado x recebido/pago)
> isolado do financeiro dos eventos.
> Melhoria pós-entrega (numeração fora das fases 0–12, como
> [14](14-kanban-reatividade-assincrona.md)…[23](23-financeiro-do-evento.md)).
>
> **Não se relaciona** com `cards`, `boards`, `events` nem `finance_sheets`. É um departamento à parte
> dentro do mesmo login, com o próprio painel e o próprio financeiro.

## TL;DR

- **Itens** = inventário próprio (LED, Mesa, Telão, Som…). Um registro por produto, com quantidade,
  valor do produto (unitário de compra) e valor de locação de referência (diária).
- **Pedidos de Locação** = cards de um Kanban próprio com etapas **fixas**:
  `Orçamento → Confirmada → Em uso → Devolvida → Encerrada` (+ `Cancelada` fora do fluxo).
- Cada pedido tem **linhas de itens** (qtd × diárias × unitário), **desconto**, **receita**
  (previsto/realizado/recebido, com recebimentos parciais) e **custos simples** (previsto/realizado,
  com pagamentos parciais).
- **Disponibilidade é informativa, nunca bloqueia**: a tela mostra quanto há disponível no período e
  avisa a falta, mas deixa seguir.
- **Sem relatórios na v1**: os números aparecem só dentro do pedido, no rodapé da lista/Kanban e na
  ficha do item.

## 1. Objetivo

A Up Music também aluga equipamentos próprios (painéis de LED, mesas, telões, som e outros). Esse
negócio é **contabilizado separadamente** dos eventos. Hoje não existe controle no sistema de:

1. quais itens existem, quanto custaram e quanto há de cada um;
2. quais locações estão em orçamento, confirmadas, em uso ou devolvidas;
3. quanto cada locação rendeu (receita) e custou (custo), e quanto já foi recebido e pago.

**Critério de sucesso:** todo o ciclo de uma locação (orçamento até o recebimento) acontece dentro do
módulo, e a qualquer momento é possível abrir um pedido e ver receita, custo, resultado e o que falta
receber ou pagar. A base de dados fica pronta para o relatório consolidado da v2 (§17).

## 2. Decisões (resultado do grilling)

| # | Tema | Decisão |
|---|---|---|
| Q1 | Identidade | Módulo **"Gestão de Locações"** no mesmo login e com os mesmos cadastros. Sem multiempresa. |
| Q2/Q20 | Granularidade do item | **Um registro por produto**, com `quantity` e `unit_cost`. Sem tabela de aquisições. Recompra com outro preço vira produto novo (ex.: "Mesa redonda (2027)"). |
| Q3 | Painel | **Kanban próprio de Pedidos de Locação**, em tabelas próprias, sem vínculo com `cards`. |
| Q4/Q23 | Etapas | **Fixas** (enum): Orçamento, Confirmada, Em uso, Devolvida, Encerrada + Cancelada. Uma única etapa final, "Encerrada". |
| Q5/Q29 | Lançamentos avulsos | **Fora da v1.** Todo o financeiro fica dentro do pedido. |
| Q6 | Valor do produto | É **investimento**, não custo operacional. Investimento do item = `quantity × unit_cost`. Não entra no resultado do pedido. |
| Q7/Q24/Q30 | Acesso | **Admin e coordenador** (sem escopo de evento) acessam tudo automaticamente. **Usuario** só entra com o checkbox no cadastro de usuário e tem acesso restrito (§11). Coordenador com escopo de evento não vê o módulo. |
| Q8 | Cliente | `empresa_id` (cadastro de Empresas, PF/PJ) **ou** nome em texto livre para cliente avulso. Pelo menos um dos dois. |
| Q9/Q19 | Campos do item | nome, descrição, código/SKU (opcional), quantidade, valor do produto, data de compra, valor de locação de referência, status (ativo/inativo), observações. **Sem** categoria, fornecedor, foto ou NF de compra. |
| Q11/Q22 | Exclusão de item | Soft delete com **motivo obrigatório**: Venda, Perda, Avaria sem conserto ou Outros (abre um campo de texto). Bloqueada se o item estiver reservado em pedido ativo. Perda parcial = editar a quantidade. |
| Q12/Q28 | Preço do pedido | Linha = qtd × diárias × unitário (sugerido pelo valor de referência, editável). Receita prevista = Σ linhas − desconto. **Sem campo de serviços adicionais**: frete e montagem vêm embutidos no valor de locação. |
| Q13/Q27 | Disponibilidade | Calculada por sobreposição de período. **Só informa e avisa, nunca bloqueia.** Sem sublocação e sem "forçar". |
| Q14 | Receita | Previsto (calculado), realizado (preenchido ao encerrar) e recebido (Σ recebimentos parciais com data, valor e fonte), mais vencimento opcional. Usa o catálogo `finance_payment_sources`. |
| Q15/Q21 | Custos | Lista simples no pedido: descrição, previsto, realizado, vencimento e pagamentos parciais, com vínculo opcional a uma linha de item. Sem categoria, fornecedor ou anexos. |
| Q16 | Movimentação | Gates por etapa (§6). Voltar etapa é permitido, exceto sair de Encerrada. |
| Q17/Q31 | Encerramento | Encerrar exige receita realizada e realizado em todos os custos (0 é aceito). O pedido trava, mas recebimentos e pagamentos continuam abertos. Reabrir: admin/coordenador. |
| Q18 | Números na v1 | Totais no pedido, rodapé da lista e das colunas do Kanban, investimento e disponibilidade na ficha do item. Sem gráficos e sem exportação. |
| Q25 | Telas | Seção **"Locações"** no menu, com Painel (Kanban), Pedidos (lista) e Itens. O pedido abre em drawer com as abas Dados · Itens · Financeiro · Histórico. |
| Q26 | Identificação | Código **LOC-0001** e histórico (log) de etapas, cancelamento, reabertura, avisos de disponibilidade e divergências de devolução. |

### 2.1 Decisões assumidas (delegadas ao implementador na sessão)

Estas regras não foram perguntadas uma a uma. O cliente pediu "siga como achar melhor":

- **Diárias** sugeridas pelo período do pedido: `max(1, dias entre start_at e end_at, arredondando para cima)`.
  Editáveis por linha. Ao alterar o período de um pedido ainda não encerrado, um SweetAlert pergunta se
  as diárias de todas as linhas devem ser recalculadas.
- **Snapshot na linha:** `item_name` e `unit_price` são gravados na linha. Mudar o item depois não altera
  pedidos existentes.
- **Item inativo** não aparece no seletor de novos pedidos, mas pedidos que já o usam continuam
  válidos.
- **Pular etapas para frente** é permitido. A Action executa os gates de cada etapa atravessada, na ordem.
- **Voltar para antes de Devolvida** limpa a conferência de devolução (`returned_quantity = null`) e
  registra isso no histórico.
- **Cancelada:** o pedido fica somente leitura, exceto recebimentos e pagamentos (sinal retido, multa).
  "Reativar" leva o pedido de volta para Orçamento (admin/coordenador).
- **Excluir pedido:** só admin/coordenador, e só em Orçamento ou Cancelada sem recebimentos nem
  pagamentos. Soft delete.
- **"A receber"** = `(receita realizada ?? receita prevista) − recebido`. Valor negativo aparece como
  "recebido a maior".

## 3. Conceito

```
rental_items (inventário)
   │ 1
   │
   │ N
rental_order_items ──N:1── rental_orders ──1:N── rental_receipts (recebimentos)
   ▲ 0..1                      │ 1
   │                           ├──1:N── rental_order_costs ──1:N── rental_cost_payments
   └───────────────────────────┤          (custos)                    (pagamentos)
                               └──1:N── rental_order_logs (histórico)

rental_orders.empresa_id ──N:1── empresas (opcional)
rental_receipts / rental_cost_payments.finance_payment_source_id ──N:1── finance_payment_sources
```

**Regra de reserva:** um pedido **reserva** os itens das suas linhas durante `[start_at, end_at)` somente
quando está em **Confirmada** ou **Em uso**. Orçamento ainda não reserva. Devolvida, Encerrada e
Cancelada liberam a reserva.

## 4. Modelo de dados

Migrations com prefixo `2026_10_20_00000N_`. Valores monetários em `decimal(12,2)` e quantidades em
`unsignedInteger`. Os nomes das tabelas seguem a convenção do projeto (inglês, plural); os models ficam
em `app/Models/` (`RentalItem`, `RentalOrder`, `RentalOrderItem`, `RentalOrderCost`,
`RentalCostPayment`, `RentalReceipt`, `RentalOrderLog`).

### 4.1 `rental_items` — inventário

```php
Schema::create('rental_items', function (Blueprint $t) {
    $t->id();
    $t->string('name', 150);
    $t->string('sku', 50)->nullable()->unique();
    $t->text('description')->nullable();
    $t->unsignedInteger('quantity');                    // quantidade total própria
    $t->decimal('unit_cost', 12, 2);                    // "valor do produto" (unitário de compra)
    $t->decimal('total_investment', 14, 2)
      ->storedAs('quantity * unit_cost');               // investimento — fora do $fillable
    $t->date('purchase_date')->nullable();
    $t->decimal('rental_price', 12, 2)->default(0);     // valor de locação de referência (diária)
    $t->boolean('active')->default(true);
    $t->text('notes')->nullable();
    $t->string('deletion_reason', 20)->nullable();      // RentalItemDeletionReason
    $t->string('deletion_reason_other', 255)->nullable();
    $t->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
    $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $t->timestamps();
    $t->softDeletes();
    $t->index(['active', 'name']);
});
```

- Obrigatórios: `name`, `quantity` (≥ 1), `unit_cost` (≥ 0) e `rental_price` (≥ 0).
- `deletion_reason_other` é obrigatório quando `deletion_reason = outros`.
- Um item excluído continua aparecendo nas linhas de pedidos antigos, com o rótulo "excluído".

### 4.2 `rental_orders` — pedido de locação (o "card")

```php
Schema::create('rental_orders', function (Blueprint $t) {
    $t->id();
    $t->string('code', 20)->nullable()->unique();       // LOC-0001, preenchido após insert
    $t->string('status', 20)->default('orcamento');     // RentalOrderStatus
    $t->unsignedInteger('position')->default(0);        // ordem dentro da coluna do Kanban
    $t->foreignId('empresa_id')->nullable()->constrained('empresas')->restrictOnDelete();
    $t->string('client_name', 150)->nullable();         // cliente avulso (texto livre)
    $t->string('location', 255)->nullable();            // local / evento (texto)
    $t->dateTime('start_at')->nullable();               // retirada / montagem
    $t->dateTime('end_at')->nullable();                 // devolução
    $t->decimal('subtotal', 14, 2)->default(0);         // Σ linhas — mantido pela Action (§7)
    $t->string('discount_type', 10)->nullable();        // RentalDiscountType: valor | percentual
    $t->decimal('discount_value', 12, 2)->default(0);
    $t->decimal('revenue_estimated', 14, 2)->default(0); // subtotal − desconto — mantido pela Action
    $t->decimal('revenue_actual', 14, 2)->nullable();   // null = ainda não realizado
    $t->date('revenue_due_date')->nullable();           // vencimento da cobrança
    $t->boolean('has_availability_warning')->default(false);
    $t->boolean('has_return_divergence')->default(false);
    $t->text('notes')->nullable();
    $t->timestamp('closed_at')->nullable();
    $t->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
    $t->timestamp('cancelled_at')->nullable();
    $t->text('cancel_reason')->nullable();
    $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $t->timestamps();
    $t->softDeletes();
    $t->index(['status', 'position']);
    $t->index(['start_at', 'end_at']);
});
```

- `subtotal` e `revenue_estimated` são **desnormalizados** (Kanban e rodapé sem `SUM` por card) e
  recalculados só pela Action `RecalculateRentalOrderTotals` (§7), dentro da mesma transação de
  qualquer escrita em linhas ou desconto.
- Cliente: `empresa_id` **ou** `client_name`. O Form Request exige um dos dois a partir de Confirmada.
  Em Orçamento os dois podem ficar vazios.

### 4.3 `rental_order_items` — linhas de itens do pedido

```php
Schema::create('rental_order_items', function (Blueprint $t) {
    $t->id();
    $t->foreignId('rental_order_id')->constrained()->cascadeOnDelete();
    $t->foreignId('rental_item_id')->constrained()->restrictOnDelete(); // item é soft-deleted
    $t->string('item_name', 150);                       // snapshot
    $t->unsignedInteger('quantity');
    $t->unsignedInteger('daily_count')->default(1);
    $t->decimal('unit_price', 12, 2);                   // snapshot do rental_price, editável
    $t->decimal('total', 14, 2)->storedAs('quantity * daily_count * unit_price');
    $t->unsignedInteger('returned_quantity')->nullable(); // conferência de devolução
    $t->unsignedInteger('position')->default(0);
    $t->timestamps();
    $t->index('rental_item_id');
});
```

### 4.4 `rental_receipts` — recebimentos (receita recebida)

```php
Schema::create('rental_receipts', function (Blueprint $t) {
    $t->id();
    $t->foreignId('rental_order_id')->constrained()->cascadeOnDelete();
    $t->decimal('amount', 12, 2);                       // > 0
    $t->date('received_at');
    $t->foreignId('finance_payment_source_id')->nullable()
      ->constrained('finance_payment_sources')->nullOnDelete();
    $t->string('notes', 255)->nullable();
    $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $t->timestamps();
});
```

### 4.5 `rental_order_costs` — custos do pedido

```php
Schema::create('rental_order_costs', function (Blueprint $t) {
    $t->id();
    $t->foreignId('rental_order_id')->constrained()->cascadeOnDelete();
    $t->foreignId('rental_order_item_id')->nullable()   // vínculo opcional a uma linha
      ->constrained('rental_order_items')->nullOnDelete();
    $t->string('description', 255);
    $t->decimal('estimated_value', 12, 2)->default(0);
    $t->decimal('actual_value', 12, 2)->nullable();     // null = ainda não realizado (≠ 0)
    $t->date('due_date')->nullable();
    $t->string('notes', 255)->nullable();
    $t->unsignedInteger('position')->default(0);
    $t->timestamps();
    $t->softDeletes();
});
```

### 4.6 `rental_cost_payments` — pagamentos dos custos

```php
Schema::create('rental_cost_payments', function (Blueprint $t) {
    $t->id();
    $t->foreignId('rental_order_cost_id')->constrained()->cascadeOnDelete();
    $t->decimal('amount', 12, 2);                       // > 0
    $t->date('paid_at');
    $t->foreignId('finance_payment_source_id')->nullable()
      ->constrained('finance_payment_sources')->nullOnDelete();
    $t->string('notes', 255)->nullable();
    $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $t->timestamps();
});
```

### 4.7 `rental_order_logs` — histórico

```php
Schema::create('rental_order_logs', function (Blueprint $t) {
    $t->id();
    $t->foreignId('rental_order_id')->constrained()->cascadeOnDelete();
    $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $t->string('type', 30);                             // RentalOrderLogType
    $t->string('from_status', 20)->nullable();
    $t->string('to_status', 20)->nullable();
    $t->text('message')->nullable();                    // motivo, justificativa, resumo
    $t->json('payload')->nullable();                    // ex.: faltas por item, divergências
    $t->timestamp('created_at')->useCurrent();
    $t->index(['rental_order_id', 'created_at']);
});
```

### 4.8 Ajuste em `users`

```php
$t->boolean('can_access_rentals')->default(false)->after('role');
```

O campo só tem efeito para o role `usuario` (§11). Na tela de Usuários, o checkbox "Acesso à Gestão de
Locações" aparece apenas quando o role selecionado é `usuario`.

## 5. Enums (`app/Domain/Enums`)

Todos com `label()`, seguindo o padrão dos enums `Finance*`.

```php
enum RentalOrderStatus: string {
    case Orcamento  = 'orcamento';   // "Orçamento"
    case Confirmada = 'confirmada';  // "Confirmada"
    case EmUso      = 'em_uso';      // "Em uso"
    case Devolvida  = 'devolvida';   // "Devolvida"
    case Encerrada  = 'encerrada';   // "Encerrada"
    case Cancelada  = 'cancelada';   // "Cancelada" — fora das colunas do fluxo

    public static function flow(): array;   // as 5 colunas do Kanban, na ordem
    public function reservesStock(): bool;  // Confirmada | EmUso
    public function isLocked(): bool;       // Encerrada | Cancelada
    public function order(): int;           // posição no fluxo (para "avançar/voltar")
    public function icon(): string;         // Font Awesome
    public function color(): string;
}

enum RentalItemDeletionReason: string {
    case Venda  = 'venda';            // "Venda"
    case Perda  = 'perda';            // "Perda"
    case Avaria = 'avaria';           // "Avaria sem conserto"
    case Outros = 'outros';           // "Outros" — exige texto
}

enum RentalDiscountType: string {
    case Valor      = 'valor';        // "R$"
    case Percentual = 'percentual';   // "%"
}

enum RentalOrderLogType: string {
    case Created             = 'created';
    case StatusChanged       = 'status_changed';
    case AvailabilityWarning = 'availability_warning';
    case ReturnChecked       = 'return_checked';
    case ReturnCleared       = 'return_cleared';
    case Closed              = 'closed';
    case Reopened            = 'reopened';
    case Cancelled           = 'cancelled';
    case Reactivated         = 'reactivated';
}
```

## 6. Máquina de estados (regra central)

Toda mudança de etapa passa por **uma única Action**, `MoveRentalOrder`, seja por drag no Kanban,
pelo seletor de etapa no drawer ou pelos botões Encerrar e Cancelar. Ela valida os gates, grava o log e
recalcula as flags.

| Transição | Quem | Gate / efeito |
|---|---|---|
| → **Confirmada** (a partir de Orçamento) | todos com acesso | Exige cliente (`empresa_id` ou `client_name`), `start_at`, `end_at` (`end_at > start_at`) e ≥ 1 linha. **Disponibilidade:** se alguma linha excede o disponível no período (§7.3), o front mostra um SweetAlert de **aviso** com a lista de faltas ("Telão: solicitado 4, disponível 2") e os botões "Confirmar mesmo assim" e "Voltar". Ao confirmar, o pedido grava o log `availability_warning` com o payload das faltas e `has_availability_warning = true`. **Nunca bloqueia.** |
| → **Em uso** | todos com acesso | Sem gate. |
| → **Devolvida** | todos com acesso | Abre o modal **Conferência de devolução**: uma linha por item com "Quantidade devolvida" (padrão = quantidade locada). Grava `returned_quantity`. Se alguma linha devolver menos que o locado, o pedido grava o log `return_checked` com as divergências e `has_return_divergence = true`, e mostra um aviso orientando a ajustar a quantidade do item, se for o caso. **Sem baixa automática.** |
| → **Encerrada** | admin, coordenador | Abre o modal **Encerramento**: receita realizada (preenchida com a prevista), realizado de cada custo e o botão "Copiar previsto para realizado" (receita e custos). Exige `revenue_actual` e `actual_value` em **todos** os custos não excluídos (0 é aceito). Grava `closed_at` e `closed_by`. |
| Voltar etapa (qualquer anterior) | todos com acesso | Permitido, exceto a partir de Encerrada e Cancelada. Voltar para antes de Devolvida limpa `returned_quantity` e `has_return_divergence` (log `return_cleared`). |
| **Reabrir** (Encerrada → Devolvida) | admin, coordenador | Motivo obrigatório. Limpa `closed_at` e `closed_by` e mantém `revenue_actual`. |
| → **Cancelada** (de qualquer etapa, exceto Encerrada) | todos com acesso | Motivo obrigatório. Grava `cancelled_at` e `cancel_reason`. Libera a reserva. |
| **Reativar** (Cancelada → Orçamento) | admin, coordenador | Limpa os campos de cancelamento. |

- **Pular etapas para frente:** permitido. Os gates das etapas atravessadas rodam em sequência
  (ex.: Orçamento → Devolvida passa pela checagem de Confirmada e abre a conferência de devolução).
- **Recálculo do aviso de disponibilidade:** `has_availability_warning` é recalculado sempre que linhas,
  período ou etapa mudam em pedidos que reservam (Confirmada/Em uso). Ele também é recalculado nos
  **outros** pedidos que reservam o mesmo item no período sobreposto, porque um pedido novo pode causar
  falta em outro (§7.3, via `RefreshAvailabilityWarnings`).
- **Travas:** em **Encerrada** e **Cancelada**, as rotas de escrita de dados, linhas, desconto,
  receita e custos respondem **422**, exceto as de **recebimentos** e **pagamentos**, que continuam
  abertas. Mesmo padrão de `FinanceController::authorizeWrite()`.

## 7. Regras de cálculo

### 7.1 Pedido (`RecalculateRentalOrderTotals`)

```
linha.total          = quantity × daily_count × unit_price          (coluna gerada)
subtotal             = Σ linhas.total
desconto (R$)        = discount_type = valor      → min(discount_value, subtotal)
                       discount_type = percentual → round(subtotal × min(discount_value,100) / 100, 2)
revenue_estimated    = subtotal − desconto
```

### 7.2 Financeiro do pedido (`RentalOrderSummaryService`, em SQL)

```
Receita   prevista   = revenue_estimated
          realizada  = revenue_actual                    (null → "—")
          recebida   = Σ rental_receipts.amount
          a receber  = (revenue_actual ?? revenue_estimated) − recebida      (< 0 → "recebido a maior")
Custos    previsto   = Σ estimated_value
          realizado  = Σ actual_value                     (algum null → mostrar "parcial")
          pago       = Σ rental_cost_payments.amount
          falta pagar= Σ (actual_value ?? estimated_value) − pago            (< 0 → "pago a maior")
Resultado previsto   = receita prevista − custos previsto
          realizado  = receita realizada − custos realizado (só quando encerrado; antes, "—")
```

O **valor do produto / investimento não entra** em nenhum cálculo do pedido (Q6).

### 7.3 Disponibilidade (`RentalAvailabilityService`)

```
reservado(item, [ini, fim), exceto pedido P) =
    Σ rental_order_items.quantity
    JOIN rental_orders o
    WHERE o.status IN ('confirmada','em_uso') AND o.deleted_at IS NULL
      AND o.start_at < :fim AND o.end_at > :ini
      AND o.id <> :P

disponível(item, período, P) = item.quantity − reservado(...)    (pode ser negativo)
falta(linha)                 = max(0, linha.quantity − disponível)
```

- Uma única query agregada (`GROUP BY rental_item_id`) atende todos os itens de uma vez, no seletor de
  itens e na checagem de confirmação. Nunca uma query por item.
- Endpoint JSON `rentals.availability` (§9) usado pelo seletor de itens do drawer. Ele mostra
  "Disponível no período: 12 de 30" e um badge laranja "Faltam 4" quando a linha excede.
- "Disponível hoje" (lista de Itens) = mesmo cálculo com o período `[agora, agora]`.

## 8. Telas e UX

Seguem [02-design-system.md](02-design-system.md):
- Font Awesome para ícones, sem emojis.
- SweetAlert2 para avisos, confirmações e toasts.
- Laranja `#ff8c1e` só em CTAs e estados ativos.

### 8.1 Menu

Nova seção **LOCAÇÕES** em `resources/views/components/sidebar.blade.php`, depois de Financeiro e
visível para quem passa no gate `access-rentals` (§11):
- **Painel** (`fa-table-columns`): Kanban;
- **Pedidos** (`fa-file-invoice-dollar`): lista;
- **Itens** (`fa-boxes-stacked`): inventário.

### 8.2 Painel (`/locacoes`)

- As 5 colunas fixas de `RentalOrderStatus::flow()`, sem o botão "adicionar coluna".
- Drag-and-drop com **SortableJS**, movimentação assíncrona no padrão de
  [14](14-kanban-reatividade-assincrona.md) (otimista, com rollback se o gate falhar).
- **Cabeçalho da coluna:** quantidade de pedidos e Σ receita prevista.
- **Card:** código, cliente, período (`dd/mm HH:mm → dd/mm HH:mm`), local, receita prevista e badges:
  - "Falta de itens" (laranja), quando `has_availability_warning`;
  - "Divergência na devolução", quando `has_return_divergence`;
  - "A receber R$ X", quando o pedido está encerrado e ainda há saldo a receber.
- **Filtros no topo:** busca (código, cliente, local), cliente, período e "mostrar cancelados" (exibe a
  coluna Cancelada ao final, recolhida).
- Botão **"Novo pedido"** (CTA laranja): cria o pedido em Orçamento e abre o drawer.

### 8.3 Drawer do pedido

Mesmo padrão visual de `boards/partials/card-panel.blade.php`. Cabeçalho com código, seletor de etapa,
botões **Encerrar** e **Cancelar** e menu (Reabrir, Reativar, Excluir conforme a permissão). Abas:

1. **Dados:**
   - cliente (busca em Empresas, com cadastro rápido via o endpoint `quick` existente, ou o toggle
     "Cliente avulso" com campo de texto);
   - local;
   - período (retirada e devolução);
   - vencimento da cobrança;
   - observações.
2. **Itens:**
   - tabela de linhas (item, qtd, diárias, unitário, total, disponível no período);
   - "Adicionar item" com busca nos itens ativos, que preenche o unitário com `rental_price` e as
     diárias pelo período;
   - desconto (R$/%);
   - subtotal, desconto e receita prevista no rodapé.
   - Depois da devolução, a coluna "Devolvido" aparece.
3. **Financeiro:**
   - cards de totais (§7.2: Receita, Custos, Resultado, A receber, Falta pagar);
   - bloco **Recebimentos** (lista + "Registrar recebimento": data, valor, fonte);
   - bloco **Custos**: lista com descrição, item vinculado (opcional), previsto, realizado, vencimento,
     pago e falta pagar. Cada custo expande para os seus **pagamentos**.
4. **Histórico:** timeline de `rental_order_logs` (usuário, data, de → para, mensagem).

Os campos monetários usam a máscara de `resources/js/money.js`.

### 8.4 Pedidos (`/locacoes/pedidos`)

- Tabela paginada (20), com busca e filtros **server-side**: etapa (múltipla), cliente, período
  (sobreposição), item e "com saldo a receber".
- Colunas: código, cliente, período, etapa, receita prevista, realizada, recebida, a receber e resultado.
- **Rodapé de totais** = agregados SQL sobre a **mesma query filtrada** (não só a página).
  Pedidos cancelados ficam fora da soma, a menos que o filtro de etapa os inclua explicitamente.
- Clicar na linha abre o mesmo drawer.

### 8.5 Itens (`/locacoes/itens`)

- **Lista** paginada com busca (nome, SKU) e filtro de status (Ativos, Inativos, Excluídos; "Excluídos"
  só para admin/coordenador, mostrando o motivo).
- **Colunas:** nome, SKU, quantidade, disponível hoje, valor de locação e, só para admin/coordenador,
  valor do produto e investimento.
- **Rodapé:** Σ investimento dos itens filtrados (admin/coordenador).
- **Formulário** (criar/editar): os campos de §4.1. O investimento aparece calculado ao lado, só para
  leitura.
- **Ficha do item:** dados, investimento e a lista dos **próximos pedidos que reservam o item** (código,
  período, quantidade).
- **Excluir:** SweetAlert com select de motivo (Venda, Perda, Avaria sem conserto, Outros). Escolher
  "Outros" exibe um input de texto obrigatório. Se o item estiver reservado em pedido Confirmada ou
  Em uso com `end_at >= agora`, a exclusão é recusada com a lista desses pedidos.

## 9. Rotas (`routes/web.php`, grupo autenticado + `can:access-rentals`)

```
GET    /locacoes                                       rentals.board
GET    /locacoes/disponibilidade                       rentals.availability         ?start&end&order
GET    /locacoes/pedidos                               rentals.orders.index
POST   /locacoes/pedidos                               rentals.orders.store
GET    /locacoes/pedidos/{pedido}                      rentals.orders.show          (drawer)
PUT    /locacoes/pedidos/{pedido}                      rentals.orders.update
DELETE /locacoes/pedidos/{pedido}                      rentals.orders.destroy
POST   /locacoes/pedidos/{pedido}/mover                rentals.orders.move          status, position
POST   /locacoes/pedidos/{pedido}/devolucao            rentals.orders.return
POST   /locacoes/pedidos/{pedido}/encerrar             rentals.orders.close
POST   /locacoes/pedidos/{pedido}/reabrir              rentals.orders.reopen
POST   /locacoes/pedidos/{pedido}/cancelar             rentals.orders.cancel
POST   /locacoes/pedidos/{pedido}/reativar             rentals.orders.reactivate
POST   /locacoes/pedidos/{pedido}/itens                rentals.orders.lines.store
PUT    /locacoes/pedidos/{pedido}/itens/{linha}        rentals.orders.lines.update
DELETE /locacoes/pedidos/{pedido}/itens/{linha}        rentals.orders.lines.destroy
POST   /locacoes/pedidos/{pedido}/recebimentos         rentals.receipts.store
DELETE /locacoes/recebimentos/{recebimento}            rentals.receipts.destroy
POST   /locacoes/pedidos/{pedido}/custos               rentals.costs.store
PUT    /locacoes/custos/{custo}                        rentals.costs.update
DELETE /locacoes/custos/{custo}                        rentals.costs.destroy
POST   /locacoes/custos/{custo}/pagamentos             rentals.payments.store
DELETE /locacoes/pagamentos/{pagamento}                rentals.payments.destroy
GET    /locacoes/itens                                 rentals.items.index
GET    /locacoes/itens/buscar                          rentals.items.search         (JSON, só ativos)
GET    /locacoes/itens/novo                            rentals.items.create
POST   /locacoes/itens                                 rentals.items.store
GET    /locacoes/itens/{item}                          rentals.items.show
GET    /locacoes/itens/{item}/editar                   rentals.items.edit
PUT    /locacoes/itens/{item}                          rentals.items.update
DELETE /locacoes/itens/{item}                          rentals.items.destroy        reason, reason_other
```

## 10. Camadas

```
app/Domain/Enums/RentalOrderStatus.php, RentalItemDeletionReason.php,
                 RentalDiscountType.php, RentalOrderLogType.php
app/Models/RentalItem.php, RentalOrder.php, RentalOrderItem.php, RentalOrderCost.php,
           RentalCostPayment.php, RentalReceipt.php, RentalOrderLog.php
app/Actions/Rentals/
    CreateRentalOrder.php              cria em Orçamento, gera o code LOC-NNNN, log created
    MoveRentalOrder.php                máquina de estados (§6) — único ponto de mudança de status
    CheckRentalReturn.php              conferência de devolução
    CloseRentalOrder.php / ReopenRentalOrder.php
    CancelRentalOrder.php / ReactivateRentalOrder.php
    SaveRentalOrderLine.php            cria/edita/remove linha + recalcula totais e avisos
    RecalculateRentalOrderTotals.php   subtotal / revenue_estimated (§7.1)
    RefreshAvailabilityWarnings.php    recalcula has_availability_warning dos pedidos afetados
    RegisterRentalReceipt.php / RegisterRentalCostPayment.php
    DeleteRentalItem.php               valida reservas ativas + grava motivo + soft delete
app/Services/Rentals/
    RentalAvailabilityService.php      §7.3
    RentalOrderSummaryService.php      §7.2 + totais do rodapé e das colunas
app/Http/Controllers/Rentals/
    RentalBoardController, RentalOrderController, RentalOrderLineController,
    RentalReceiptController, RentalCostController, RentalCostPaymentController,
    RentalItemController, RentalAvailabilityController
    (base abstrata RentalController::authorizeWrite(): 403 sem permissão / 422 pedido travado)
app/Http/Requests/Rentals/
    StoreRentalItemRequest, UpdateRentalItemRequest, DestroyRentalItemRequest,
    StoreRentalOrderRequest, UpdateRentalOrderRequest, MoveRentalOrderRequest,
    SaveRentalOrderLineRequest, CheckRentalReturnRequest, CloseRentalOrderRequest,
    CancelRentalOrderRequest, ReopenRentalOrderRequest,
    StoreRentalReceiptRequest, SaveRentalCostRequest, StoreRentalCostPaymentRequest
app/Policies/RentalItemPolicy.php, RentalOrderPolicy.php
resources/views/locacoes/            board, pedidos/index, itens/{index,form,show}, partials/_drawer
resources/js/rentals-board.js, rentals-drawer.js
```

Validação sempre em Form Requests e regra de negócio sempre em Actions/Services (convenções do projeto).

## 11. Permissões

Gates em `AuthServiceProvider`:

```php
Gate::define('access-rentals', fn (User $u) =>
    $u->isAdmin()
    || ($u->isCoordenador() && ! $u->isEventScoped())
    || ($u->role === UserRole::Usuario && $u->can_access_rentals));

Gate::define('manage-rentals', fn (User $u) =>
    $u->isAdmin() || ($u->isCoordenador() && ! $u->isEventScoped()));
```

| Ação | admin / coordenador (sem escopo) | usuario habilitado |
|---|---|---|
| Ver Painel, Pedidos, Itens | sim | sim |
| Criar/editar pedido, linhas, desconto, custos | sim | sim |
| Mover etapas (exceto Encerrar), conferência de devolução, cancelar | sim | sim |
| Registrar/excluir recebimentos e pagamentos | sim | sim |
| **Encerrar**, **Reabrir**, **Reativar**, **Excluir pedido** | sim | **não** |
| **Criar/editar/excluir item**, ver itens excluídos | sim | **não** |
| Ver **valor do produto**, **investimento**, data de compra | sim | **não** (colunas e campos omitidos no servidor, não só ocultos no front) |

- Coordenador com escopo de evento (spec 20) **não** acessa o módulo, mesmo que o checkbox exista.
- Os botões que o usuário não pode usar não são renderizados. A rota também responde 403.

## 12. Performance

- Kanban: uma query por coluna (ou uma só, agrupada) com `subtotal` e `revenue_estimated`
  desnormalizados, eager-load de `empresa:id,trade_name,corporate_name` e sem `SUM` por card.
- Totais das colunas e do rodapé: `SUM` agregado em SQL sobre a query filtrada.
- Disponibilidade: uma query agregada por chamada (§7.3), apoiada nos índices
  `rental_orders(start_at, end_at)`, `rental_orders(status, position)` e
  `rental_order_items(rental_item_id)`.
- `RefreshAvailabilityWarnings` atua só nos pedidos que reservam os itens alterados, com período
  sobreposto.

## 13. Testes (`tests/Feature/Rentals/`, PHPUnit — sem front/e2e/carga)

- **Itens:**
  - CRUD com validação;
  - `total_investment` gerado;
  - usuario não cria/edita/exclui e não recebe `unit_cost`/`total_investment` no JSON nem no HTML;
  - exclusão exige motivo (e texto quando "Outros");
  - exclusão bloqueada com reserva ativa e permitida quando só há pedidos passados.
- **Disponibilidade:**
  - sobreposição de períodos (bordas exatas não sobrepõem);
  - só Confirmada/Em uso reservam;
  - o pedido atual não conta contra si mesmo;
  - resultado negativo.
- **Máquina de estados:**
  - gate de Confirmada (cliente, período, linha);
  - confirmar com falta **não bloqueia** e grava o log e a flag;
  - pular etapas roda os gates;
  - voltar limpa a devolução;
  - divergência de devolução grava o log e a flag;
  - encerrar exige realizado em receita e custos;
  - usuario não encerra (403);
  - reabrir e reativar só para admin/coordenador;
  - cancelar exige motivo.
- **Travas:** pedido Encerrado/Cancelado responde 422 em linhas, custos e dados, e aceita recebimento e
  pagamento.
- **Cálculos:**
  - total da linha;
  - desconto em valor (limitado ao subtotal) e percentual;
  - a receber, falta pagar e resultado;
  - rodapé da lista igual à soma da query filtrada.
- **Acesso:**
  - admin e coordenador sem escopo entram;
  - coordenador escopado recebe 403;
  - usuario sem checkbox recebe 403 e com checkbox entra.
- **Código:** `LOC-0001` sequencial e único.

## 14. Critérios de aceite

- [ ] Itens: cadastrar, editar, inativar e excluir com motivo (Outros abre um input). Investimento =
      qtd × valor do produto.
- [ ] Usuario habilitado não vê valor do produto nem investimento em nenhuma tela ou resposta.
- [ ] Criar pedido no Painel, preencher cliente (Empresa ou avulso), período e itens. Unitário sugerido
      pelo valor de locação e diárias pelo período.
- [ ] Desconto em R$ ou % e receita prevista recalculada automaticamente.
- [ ] Seletor de itens mostra o disponível no período e avisa a falta, sem bloquear.
- [ ] Mover no Kanban com os gates de §6, avisos via SweetAlert e rollback visual quando o gate recusa.
- [ ] Conferência de devolução registra as quantidades e sinaliza divergência.
- [ ] Encerrar exige realizado (com "copiar previsto") e trava o pedido. Recebimentos e pagamentos
      continuam possíveis.
- [ ] Reabrir, reativar e excluir só para admin/coordenador, com log.
- [ ] Recebimentos e pagamentos parciais com data, valor e fonte. A receber e falta pagar corretos.
- [ ] Lista de Pedidos com paginação, busca, filtros server-side e rodapé de totais da query filtrada.
- [ ] Histórico mostra todas as transições, avisos e motivos.
- [ ] Menu "Locações" visível só para quem passa em `access-rentals`. Checkbox na tela de Usuários.
- [ ] Sem emojis; ícones Font Awesome; alertas SweetAlert2.
- [ ] `php artisan test` verde, `pint --dirty` e `npm run build` limpos.

## 15. Ordem de implementação sugerida

1. Migrations, enums, models e o gate/checkbox em Usuários.
2. Itens (CRUD, exclusão com motivo, visibilidade por perfil).
3. `RentalAvailabilityService` + endpoint.
4. Pedido: criar/editar, linhas, desconto, totais (drawer, abas Dados e Itens).
5. `MoveRentalOrder` + Painel Kanban (drag, gates, devolução, encerramento, cancelamento, histórico).
6. Financeiro do pedido (recebimentos, custos, pagamentos, cards de totais).
7. Lista de Pedidos com filtros e rodapé.
8. Testes de §13 e atualização do CHECKLIST.

## 16. Fora de escopo (v1)

- **Relatórios** consolidados, gráficos e exportação (XLSX/PDF).
- Lançamentos **avulsos** (receitas e custos fora de pedido, como manutenção ou venda de item).
- Categorias de custo, fornecedor no custo e anexos (NF, contrato, comprovante).
- Foto do item, NF de compra, fornecedor da compra, categoria de item.
- Histórico de aquisições/lotes e baixa parcial com motivo (perda parcial = editar a quantidade).
- Sublocação de terceiros e bloqueio de disponibilidade.
- Serviços adicionais cobrados à parte (frete e montagem ficam embutidos no valor de locação).
- Vínculo com eventos, cards ou o financeiro dos eventos; multiempresa.
- Notificações e alertas de vencimento; geração de contrato/orçamento em PDF.

## 17. v2 (registrado para não perder)

O modelo da v1 já sustenta, sem migração destrutiva:
- **Relatório para os sócios:** receita x custo x resultado por período (por `closed_at` ou
  `start_at`), recebido x a receber e fluxo de caixa (`received_at` / `paid_at`).
- **Retorno por item:** receita atribuída por linha (`rental_order_items.total`, proporcional ao
  desconto) contra `total_investment`, mostrando o % recuperado (Q6).
- **Taxa de ocupação** por item (diárias locadas / diárias possíveis).
- Lançamentos avulsos, exportação XLSX (reusar `phpoffice/phpspreadsheet`).
