# 23 — Financeiro do Evento (substituição da planilha)

> **Modelo recomendado:** `opus` (Opus 5) — módulo de maior complexidade da fase pós-entrega: 7 tabelas
> novas, colunas geradas no banco, ponte com o Kanban (anexos) e uma tela em grade tipo planilha.
> Melhoria pós-entrega (numeração fora das fases 0–12, como
> [14](14-kanban-reatividade-assincrona.md)…[22](22-notificacoes.md)).
>
> **Substitui** a [specs/09 — Planejamento Financeiro](09-planejamento-financeiro.md) como módulo
> financeiro do produto. Ver §14 para o destino do que já existe.

## 1. Objetivo

Hoje o controle financeiro de um evento acontece **duas vezes**:

1. no Kanban, onde os arquivos (orçamento, contrato, nota fiscal, comprovante, ART, boleto) chegam
   como anexos nos cards; e
2. numa **planilha Excel por evento** (`referencia/FINANCEIRO - MODELO (1).xlsx`), onde tudo é
   redigitado — item, fornecedor, quantidade, valor previsto, valor realizado, quem pagou, o que
   falta pagar e quais documentos existem.

Este módulo elimina a etapa 2. O evento passa a ter uma **planilha financeira dentro do sistema**,
com as mesmas três visões do arquivo Excel (**Resumo Geral**, **Receitas**, **Custos**), e o card do
Kanban **empurra** os comprovantes para a linha de custo correspondente — sem reupload, sem redigitar.

**Critério de sucesso:** ao final de um evento, nada do que hoje é preenchido no arquivo
`FINANCEIRO - MODELO` precisa ser digitado fora do sistema; o Excel vira, no máximo, um **export**
para terceiros (contabilidade, sócios, cliente).

## 2. Diagnóstico — o que a planilha faz hoje

O arquivo modelo tem quatro abas. Mapa direto para o sistema:

| Aba do Excel | O que é | Destino no sistema |
|---|---|---|
| **RESUMO GERAL** | Receita/Custo/Resultado previsto x realizado, custo por item (categoria), andamento (pago / falta pagar) e acerto de sócios | Tela **Resumo** (§8.2), tudo agregado por SQL |
| **RECEITAS** | Linhas de entrada: ingressos, lounges, estacionamento, bar, patrocínios; previsto, realizado, recebido, falta receber, recebido por, obs | Tabela `finance_revenues` + tela **Receitas** (§8.3) |
| **CUSTOS** | 176 linhas item/descrição, status, ART, empresa, autorizado por, diárias, quant., 3 cenários de valor (Previsto 1, Previsto 2, Realizado), 5 colunas de "pago por", pago/falta pagar e 6 colunas de controle documental | Tabelas `finance_cost_items`, `finance_payments`, `finance_documents` + tela **Custos** (§8.4) |
| **Página5** | Agenda semanal solta (datas + "atividades da próxima semana") | **Fora de escopo** — é resíduo de outro controle; o acompanhamento de tarefas já é o Kanban |

Detalhes extraídos do arquivo que viram regra de sistema:

- **Fórmula das linhas de custo:** `TOTAL = VALOR UNIT. × QUANT. × DIÁRIAS` — repetida nos três
  cenários (`J=I*H*G`, `L=K*H*G`, `N=M*H*G`). **Diárias e quantidade são compartilhadas** pelos três;
  só o valor unitário muda de cenário para cenário. É exatamente o que o cliente descreve: *"quando
  vem para o previsto 2, eu considero só o valor unitário e ele vai puxar a informação daqui"*.
- **Previsto 2 é opcional.** *"Às vezes eu consigo só com o previsto, ou eu tenho que ter o previsto
  que é quando está sendo idealizado na coleta de orçamento e o que realmente aconteceu."* → flag por
  evento (§4.1), não coluna sempre visível.
- **Listas fechadas (data validation) já existentes no arquivo:**
  - `A8:A183` (ITEM) → `LICENÇAS E TAXAS, PROJETO, LOGÍSTICA, DIVULGAÇÃO, MÍDIA, ESTRUTURA GERAL,
    CENOGRAFIA, RH, SERVIÇOS, CAMARIM, BAR, OUTROS, ARTÍSTICO, ESTRUTURA PALCO,
    ESTRUTURA CAM EMPRESARIAL, RODEIO, ESTRUTURA CAMAROTE, PREFEITURA` — é a **categoria**, e o
    cliente pediu explicitamente para **reaproveitar a categoria que já existe** no sistema
    (`fornecedor_categorias`, specs/15).
  - `C8:C183` (STATUS) → `ORÇAMENTO, AGUARDANDO CONTRATO, CONTRATO OK | FALTA NOTA,
    CONTRATO OK | NOTA OK, NÃO APLICADO`.
  - `D8:D183` (ART) → `AGUARDANDO ENVIO, NÃO TEM, ART OK`. (No áudio o cliente chama de "RT"; no
    arquivo a coluna é **ART** — o sistema usa **ART**, com "RRT" e "TRT" como sinônimos de busca.)
- **Grupos de pagamento** (colunas O–S): `CAIXA EVENTO, SÓCIO 1, SÓCIO 2, TICKETEIRA, BAR`, e daí
  saem `PAGO` e `FALTA PAGAR`.
- **Controle documental** (colunas V–AA): `ORÇAMENTO, CONTRATO, NOTA FISCAL, COMPROVANTE, ART,
  BOLETO`. É o *"X da questão"* do áudio — e é justamente o que já existe como anexo no card.

## 3. Conceito e decisões

- **A unidade é o evento, não o "plano".** Toda planilha pertence a um `events.id` (1:1). O evento já
  é o eixo do Kanban (`cards.event_id`), do banco de preços (`price_records.event_id`) e do escopo de
  coordenador (specs/20) — usar o mesmo eixo faz o financeiro herdar permissão e filtro de graça.
- **O documento não é copiado, é referenciado.** Um comprovante enviado no card **não vira um segundo
  arquivo** no financeiro: `finance_documents` aponta para o `card_attachments.id` já existente. Fonte
  única, storage único, e o "trabalho dobrado" some por construção. Upload direto no financeiro
  continua possível para o que nunca passou por card (taxa, guia, boleto avulso).
- **A linha de custo é editável mesmo quando veio de um card.** O card sugere; o financeiro decide.
  Nenhum campo fica travado por origem — o vínculo (`card_id`) serve para rastrear e re-sincronizar
  documentos, não para bloquear edição.
- **Totais são calculados pelo banco.** `TOTAL = unitário × quantidade × diárias` vira **coluna
  gerada STORED** (§4.3): impossível existir linha com total fora de sincronia, e os `SUM()` do resumo
  leem coluna indexável. Agregações do resumo são `SUM`/`GROUP BY`, nunca laço em Blade (regra de
  arquitetura do projeto).
- **Grupo de pagamento é catálogo, não coluna.** "SÓCIO 1"/"SÓCIO 2" mudam de evento para evento e um
  mesmo item pode ser pago em partes por fontes diferentes. Cinco colunas fixas viram uma tabela de
  **pagamentos** (`finance_payments`) contra um **catálogo de fontes** (`finance_payment_sources`).
  Ganha-se pagamento parcial, data de pagamento e auditoria — coisas que a planilha não tem.
- **Status é sugerido, nunca imposto.** O sistema deriva o STATUS e o ART a partir dos documentos
  presentes, mas para de derivar assim que alguém edita à mão (§6.4). O usuário continua dono do campo.
- **Sem emojis; Font Awesome + SweetAlert2**, cores da marca (preto + `#ff8c1e`), conforme
  [`02-design-system.md`](02-design-system.md).

## 4. Modelo de dados

Sete tabelas novas, prefixo `finance_`. Padrão do projeto: nomes em inglês no plural, rótulos de UI
em PT-BR.

```
events 1──1 finance_sheets 1──* finance_revenues
                           1──* finance_cost_items 1──* finance_payments ──* finance_payment_sources
                                                   1──* finance_documents ──? card_attachments
                           1──* finance_partner_settlements
fornecedor_categorias 1──* finance_item_presets
```

### 4.1 `finance_sheets` — a planilha do evento

`database/migrations/2026_09_01_000001_create_finance_sheets_table.php`:

```php
Schema::create('finance_sheets', function (Blueprint $table) {
    $table->id();
    $table->foreignId('event_id')->unique()->constrained('events')->cascadeOnDelete();
    // "Previsto 2" (refinamento pós-coleta de orçamentos). Desligado por padrão: eventos simples
    // usam só Previsto 1, e a grade esconde o bloco inteiro quando falso.
    $table->boolean('uses_second_estimate')->default(false);
    $table->string('status', 20)->default('aberto');       // FinanceSheetStatus: aberto | fechado
    $table->timestamp('closed_at')->nullable();
    $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

- **Criada sob demanda** (`firstOrCreate`) na primeira vez que alguém abre o financeiro do evento ou
  que um card é enviado ao financeiro. Não há CRUD de "criar planilha".
- `status = fechado` congela a prestação de contas: bloqueia edição de linhas, pagamentos e exclusão
  de anexos vinculados (§6.6). Reabrir é ação de `admin`.

### 4.2 `finance_payment_sources` — grupos de pagamento (catálogo global)

`…_000002_create_finance_payment_sources_table.php`:

```php
Schema::create('finance_payment_sources', function (Blueprint $table) {
    $table->id();
    $table->string('name', 80);
    $table->string('kind', 20)->default('caixa');   // caixa | socio | ticketeira | bar | outro
    // Sócio opcionalmente vinculado a um usuário — habilita o acerto de sócios por pessoa (§4.7).
    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->boolean('active')->default(true)->index();
    $table->integer('position')->default(0);
    $table->timestamps();
});
```

Seed inicial (espelha as colunas O–S do modelo): `Caixa do Evento` (caixa), `Sócio 1` (socio),
`Sócio 2` (socio), `Ticketeira` (ticketeira), `Bar` (bar). Gerenciável em
**Financeiro › Configurações** por `admin`.

### 4.3 `finance_cost_items` — a aba CUSTOS

`…_000003_create_finance_cost_items_table.php`:

```php
Schema::create('finance_cost_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('finance_sheet_id')->constrained()->cascadeOnDelete();

    // ITEM (coluna A) — reaproveita a categoria de fornecedor que já existe (specs/15).
    $table->foreignId('fornecedor_categoria_id')->nullable()
          ->constrained('fornecedor_categorias')->nullOnDelete();
    $table->string('description', 180);                    // DESCRIÇÃO (coluna B)

    $table->string('status', 30)->default('orcamento');     // FinanceCostStatus (coluna C)
    $table->boolean('status_auto')->default(true);          // ainda derivado dos documentos? (§6.4)
    $table->string('art_status', 20)->default('nao_tem');   // FinanceArtStatus (coluna D)

    // EMPRESA (coluna E): fornecedor cadastrado ou, quando não houver cadastro, texto livre.
    $table->foreignId('fornecedor_id')->nullable()->constrained('fornecedores')->nullOnDelete();
    $table->string('supplier_name', 180)->nullable();

    // AUTORIZADO POR (coluna F): usuário do sistema ou texto livre (autorização externa).
    $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
    $table->string('authorized_by_name', 120)->nullable();

    // Compartilhados pelos três cenários — é o que a planilha faz (G e H entram nas 3 fórmulas).
    $table->decimal('daily_count', 8, 2)->default(1);       // DIÁRIAS (coluna G)
    $table->decimal('quantity', 10, 2)->default(1);         // QUANT.  (coluna H)

    $table->decimal('unit_estimated_1', 15, 2)->default(0); // VALOR UNIT. previsto 1 (I)
    $table->decimal('unit_estimated_2', 15, 2)->nullable(); // VALOR UNIT. previsto 2 (K) — null = não usado
    $table->decimal('unit_actual', 15, 2)->nullable();      // VALOR UNIT. realizado  (M) — null = não realizado

    // Colunas geradas STORED: reproduzem as fórmulas J/L/N e tornam impossível um total
    // dessincronizado. Não entram em $fillable; ler com refresh() após salvar.
    $table->decimal('total_estimated_1', 15, 2)
          ->storedAs('unit_estimated_1 * quantity * daily_count');
    $table->decimal('total_estimated_2', 15, 2)
          ->storedAs('COALESCE(unit_estimated_2, 0) * quantity * daily_count');
    $table->decimal('total_actual', 15, 2)
          ->storedAs('COALESCE(unit_actual, 0) * quantity * daily_count');

    // Origem no Kanban (§6). Nullable: linha nascida direto no financeiro (taxa, guia) não tem card.
    $table->foreignId('card_id')->nullable()->constrained('cards')->nullOnDelete();

    $table->text('notes')->nullable();
    $table->integer('position')->default(0);
    $table->timestamps();
    $table->softDeletes();

    $table->index(['finance_sheet_id', 'position']);
    $table->index(['finance_sheet_id', 'fornecedor_categoria_id']);   // "custo por item" do resumo
    $table->index(['finance_sheet_id', 'status']);
    $table->index('card_id');
});
```

> **Colunas geradas em MariaDB 10.4** (banco local do projeto): `STORED` é suportado desde o 10.2 e a
> expressão aqui é determinística, então indexa e agrega normalmente. Guardas obrigatórias no model:
> as três `total_*` ficam **fora de `$fillable`**, e qualquer `create()`/`update()` deve ser seguido de
> `refresh()` quando o total for usado na resposta. Se em algum ambiente o `storedAs` falhar, o
> fallback é calcular na Action e gravar em colunas comuns — **nunca** calcular no Blade.

**Por que `unit_actual` e `unit_estimated_2` são nullable e `unit_estimated_1` não:** "0" e "ainda não
tem" são estados diferentes. Uma linha com realizado `0,00` significa "saiu de graça"; realizado
`null` significa "ainda não aconteceu" — e o resumo precisa distinguir os dois para não mostrar
economia inexistente.

### 4.4 `finance_payments` — PAGO POR / PAGO / FALTA PAGAR

`…_000004_create_finance_payments_table.php`:

```php
Schema::create('finance_payments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('finance_cost_item_id')->constrained()->cascadeOnDelete();
    $table->foreignId('finance_payment_source_id')->constrained('finance_payment_sources');
    $table->decimal('amount', 15, 2);
    $table->date('paid_at')->nullable();
    $table->string('notes', 255)->nullable();
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();

    $table->index(['finance_cost_item_id', 'finance_payment_source_id']);
});
```

- **PAGO** de uma linha = `SUM(finance_payments.amount)`.
- **FALTA PAGAR** = `total_actual − PAGO` (piso em 0 na exibição; valor negativo aparece como
  *"pago a maior"* em laranja, porque é sinal de erro de lançamento e precisa ser visto).
- Validação: `amount > 0`; a soma dos pagamentos **pode** exceder `total_actual`, mas o formulário
  avisa (SweetAlert2 de confirmação) antes de gravar.

### 4.5 `finance_documents` — o CONTROLE (colunas V–AA)

`…_000005_create_finance_documents_table.php`:

```php
Schema::create('finance_documents', function (Blueprint $table) {
    $table->id();
    $table->foreignId('finance_cost_item_id')->constrained()->cascadeOnDelete();
    $table->string('kind', 20);          // FinanceDocumentKind: orcamento|contrato|nota_fiscal|comprovante|art|boleto

    // Forma A — documento que já vive no card (o caso comum): referência, sem cópia de arquivo.
    $table->foreignId('card_attachment_id')->nullable()
          ->constrained('card_attachments')->cascadeOnDelete();

    // Forma B — upload feito direto no financeiro (guia, boleto, taxa que nunca teve card).
    $table->string('original_name', 255)->nullable();
    $table->string('path', 255)->nullable();
    $table->string('mime', 120)->nullable();
    $table->unsignedInteger('size')->default(0);

    $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();

    // Idempotência da ponte: reenviar o mesmo card não duplica documento.
    $table->unique(['finance_cost_item_id', 'card_attachment_id']);
    $table->index(['finance_cost_item_id', 'kind']);
});
```

- **Invariante:** exatamente um dos dois lados preenchido — `card_attachment_id` **ou** `path`.
  Garantido na Action (`CreateFinanceDocument`) e coberto por teste.
- Servir o arquivo reusa a lógica segura já existente em `CardController::downloadAttachment()`:
  `Content-Type` derivado do conteúdo (`finfo`), nunca da coluna `mime`, mais
  `X-Content-Type-Options: nosniff`. Extrair isso para `app/Support/ServesStoredFile.php` (trait) e
  usar nos dois controllers, em vez de duplicar.
- Um mesmo `kind` pode ter **vários** arquivos (dois orçamentos concorrentes, NF + NF de correção). O
  "chip" do controle fica verde com o contador quando há ≥ 1.

### 4.6 `finance_revenues` — a aba RECEITAS

`…_000006_create_finance_revenues_table.php`:

```php
Schema::create('finance_revenues', function (Blueprint $table) {
    $table->id();
    $table->foreignId('finance_sheet_id')->constrained()->cascadeOnDelete();
    $table->string('category', 40);                       // FinanceRevenueCategory (coluna A)
    // "vai com a descrição do que entrou de patrocínio" — nome do patrocinador/lote/cota.
    $table->string('description', 180)->nullable();
    $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete(); // patrocinador cadastrado
    $table->decimal('estimated_value', 15, 2)->default(0); // VALOR PREVISTO (B)
    $table->decimal('actual_value', 15, 2)->default(0);    // VALOR REALIZADO (C)
    $table->decimal('received_value', 15, 2)->default(0);  // RECEBIDO (D)
    $table->decimal('pending_value', 15, 2)->storedAs('actual_value - received_value'); // FALTA RECEBER (E)
    $table->foreignId('finance_payment_source_id')->nullable()
          ->constrained('finance_payment_sources');        // RECEBIDO POR (F)
    $table->date('received_at')->nullable();
    $table->string('notes', 255)->nullable();              // OBS (G)
    $table->integer('position')->default(0);
    $table->timestamps();
    $table->softDeletes();

    $table->index(['finance_sheet_id', 'position']);
    $table->index(['finance_sheet_id', 'category']);
});
```

Ao criar a planilha, semear as linhas do modelo (uma por categoria não-patrocínio, valores zerados) —
o usuário abre a aba e já encontra a estrutura conhecida, em vez de uma tela vazia. Patrocínio entra
como linha adicionada sob demanda (é o único que se repete N vezes no modelo, uma por patrocinador).

### 4.7 `finance_partner_settlements` — ACERTO SÓCIOS

`…_000007_create_finance_partner_settlements_table.php`:

```php
Schema::create('finance_partner_settlements', function (Blueprint $table) {
    $table->id();
    $table->foreignId('finance_sheet_id')->constrained()->cascadeOnDelete();
    $table->foreignId('finance_payment_source_id')->nullable()
          ->constrained('finance_payment_sources');     // o sócio, quando é fonte cadastrada
    $table->string('partner_name', 120);
    $table->decimal('percentage', 5, 2)->default(0);    // PORCENTAGEM
    $table->decimal('amount', 15, 2)->default(0);       // TOTAL
    // amount = resultado realizado × percentage, exceto quando alguém digita o valor à mão.
    $table->boolean('manual_amount')->default(false);
    $table->timestamps();
});
```

Regras: `percentage` entre 0 e 100; a tela avisa (sem bloquear) quando a soma dos percentuais ≠ 100.
Quando `manual_amount = false`, `amount` é recalculado a cada abertura do resumo a partir do
**resultado realizado** (§7).

### 4.8 `finance_item_presets` — o catálogo de descrições do modelo

`…_000008_create_finance_item_presets_table.php`:

```php
Schema::create('finance_item_presets', function (Blueprint $table) {
    $table->id();
    $table->foreignId('fornecedor_categoria_id')->constrained()->cascadeOnDelete();
    $table->string('description', 180);
    $table->boolean('active')->default(true)->index();
    $table->timestamps();
    $table->unique(['fornecedor_categoria_id', 'description']);
});
```

Alimenta o **autocomplete da coluna DESCRIÇÃO** com as 168 descrições que o modelo já traz
(`TENDA 10X10`, `BRIGADISTAS | EVENTO`, `TAXA | ECAD`, …), agrupadas pela categoria escolhida na
coluna ITEM. Não é lista fechada: o usuário pode digitar qualquer descrição, e uma descrição nova
pode ser promovida a preset com um clique (`admin`/`coordenador`).

Fonte do seed: [`specs/data/financeiro-itens-modelo.csv`](data/financeiro-itens-modelo.csv) (168
linhas `categoria,descricao`, extraídas do arquivo modelo). Ver Apêndice A.

### 4.9 Ajustes em tabelas existentes

`…_000009_add_finance_support_columns.php`:

```php
// Quadro que alimenta o financeiro (o quadro "Financeiro" do seeder). Mesmo padrão de
// boards.allows_supplier_form (specs/19): flag no quadro, configurável na tela de config.
Schema::table('boards', fn (Blueprint $t) => $t->boolean('feeds_finance')->default(false)->after('active'));
```

E no seeder de categorias (`FornecedorCategoriaSeeder`, novo): acrescentar, com `firstOrCreate` por
nome, as categorias do modelo que ainda não existem em `fornecedor_categorias` —
`Licenças e Taxas`, `Mídia`, `RH`, `Serviços`, `Camarim`, `Bar`, `Outros`, `Artístico`,
`Estrutura Palco`, `Estrutura Cam Empresarial`, `Rodeio`, `Estrutura Camarote`, `Prefeitura`.
As existentes (`Cenografia`, `Divulgação`, `Estrutura Geral`, `Logística`, `Projeto`, `Limpeza`,
`Segurança`, `Som`, `Estrutura Lounge`) são **reaproveitadas como estão** — nada é renomeado nem
duplicado, para não quebrar `price_records` nem `fornecedores`.

## 5. Enums (`app/Domain/Enums`)

Todos com `label()` em PT-BR e, quando fizer sentido, `icon()` (Font Awesome) e `color()` (token do
design system) — mesmo formato de `NotificationType` (specs/22).

```php
enum FinanceCostStatus: string
{
    case Orcamento          = 'orcamento';               // ORÇAMENTO
    case AguardandoContrato = 'aguardando_contrato';     // AGUARDANDO CONTRATO
    case ContratoFaltaNota  = 'contrato_ok_falta_nota';  // CONTRATO OK | FALTA NOTA
    case ContratoNotaOk     = 'contrato_ok_nota_ok';     // CONTRATO OK | NOTA OK
    case NaoAplicado        = 'nao_aplicado';            // NÃO APLICADO
}

enum FinanceArtStatus: string
{
    case AguardandoEnvio = 'aguardando_envio';
    case NaoTem          = 'nao_tem';
    case Ok              = 'art_ok';
}

enum FinanceDocumentKind: string
{
    case Orcamento   = 'orcamento';
    case Contrato    = 'contrato';
    case NotaFiscal  = 'nota_fiscal';
    case Comprovante = 'comprovante';
    case Art         = 'art';
    case Boleto      = 'boleto';
}

enum FinanceRevenueCategory: string
{
    case IngressosTicketeira = 'ingressos_ticketeira';   // INGRESSOS | TICKETEIRA
    case LoungesTicketeira   = 'lounges_ticketeira';     // LOUNGES | TICKETEIRA
    case LoungesAvulso       = 'lounges_avulso';         // LOUNGES | AVULSO
    case Estacionamento      = 'estacionamento';
    case BarEmpresa          = 'bar_empresa';            // BAR | EMPRESA
    case BarDrinks           = 'bar_drinks';             // BAR | DRINKS
    case Balinheiros         = 'balinheiros';
    case Copos               = 'copos';
    case Alimentacao         = 'alimentacao';
    case Patrocinio          = 'patrocinio';
    case Outros              = 'outros';
}

enum FinanceSheetStatus: string { case Aberto = 'aberto'; case Fechado = 'fechado'; }
```

**Alteração em `App\Domain\Enums\AttachmentKind`** (arquivo existente): acrescentar
`case Art = 'art';` e `case Boleto = 'boleto';`, e incluí-los em `selectable()` — hoje o usuário não
consegue marcar um anexo como ART ou boleto, e sem isso dois dos seis controles do modelo nunca
chegariam preenchidos pela ponte. `Minuta` continua fora de `selectable()` pelo motivo já documentado
no enum.

## 6. A ponte Kanban → Financeiro (o coração do módulo)

> *"Quando a gente chega nessa parte do financeiro (no Kanban) deveria ter alguma forma de enviar
> esses comprovantes para o módulo financeiro."*

### 6.1 Três caminhos, uma Action

Tudo converge para `app/Actions/Finance/SyncCardToFinance.php`:

```php
public function execute(Card $card, ?User $actor = null, array $overrides = []): FinanceCostItem
```

1. **Manual (principal)** — botão **"Enviar para o Financeiro"** no painel do card
   (`resources/views/boards/partials/card-panel.blade.php`), ao lado de "Compartilhar Card"
   (specs/18). Abre um modal de confirmação (§6.3).
2. **Automático por movimentação** — ao mover/transferir um card para um quadro com
   `boards.feeds_finance = true`, `MoveCard` e `TransferCard` chamam a Action. É o gatilho que faz o
   fluxo "chegou no Financeiro" acontecer sem ninguém lembrar de clicar.
3. **Contínuo** — `CardAttachmentObserver` (novo): anexo criado num card **já vinculado** a uma linha
   de custo vira `finance_documents` na hora. Sem isso, o comprovante que chega depois do envio
   inicial ficaria só no card — e a planilha voltaria a divergir.

### 6.2 O que a Action faz

```
1. Exige card->event_id. Sem evento → 422 "Vincule o card a um evento antes de enviar ao Financeiro."
   (o modal já oferece o select de evento para resolver na hora)
2. FinanceSheet::firstOrCreate(['event_id' => $card->event_id])
3. Linha de custo:
   - existe FinanceCostItem where card_id = card->id (na planilha do evento)?  → reusa
   - senão cria, pré-preenchida a partir do card:
       fornecedor_categoria_id ← card->fornecedor?->fornecedor_categoria_id
       description             ← card->title
       fornecedor_id           ← card->fornecedor_id
       supplier_name           ← card->fornecedor?->name (snapshot, se não houver cadastro)
       authorized_by           ← aprovador da coluna (specs/17), senão card->assignee_id
       unit_estimated_1        ← card->estimated_value ?? 0
       unit_actual             ← valor negociado do card (§6.5)
       quantity / daily_count  ← 1 (ajustável na grade; o card não tem essa granularidade)
4. Documentos: para cada card_attachments com kind mapeável (§6.4), cria finance_documents
   referenciando o anexo. O unique (item, attachment) torna a operação idempotente.
5. Deriva status e art_status (§6.4) se status_auto = true.
6. Registra um comentário automático no card: "Enviado ao Financeiro — linha #{id} ({evento})",
   com link para /financeiro/eventos/{evento}/custos#linha-{id}.
7. Notifica (specs/22, novo NotificationType::CardSentToFinance) o responsável do card e o
   coordenador do evento, exceto o próprio autor.
```

Tudo dentro de `DB::transaction`. A Action é **idempotente**: rodar duas vezes no mesmo card não
duplica linha nem documento, só sincroniza o que apareceu depois.

### 6.3 Modal "Enviar para o Financeiro"

Ao clicar, antes de gravar, o usuário vê e confirma:

- **Evento** (select; obrigatório — pré-selecionado com `card->event_id`).
- **Item/Categoria** (select de `fornecedor_categorias`, pré-selecionado pela categoria do fornecedor).
- **Descrição** (texto, pré-preenchido com o título do card, com autocomplete de `finance_item_presets`).
- **Anexos a enviar** — lista dos anexos do card com o tipo de cada um, todos marcados por padrão;
  anexos `geral`/`minuta` aparecem **desmarcados** com um select para classificar (é o único ponto
  onde o usuário precisa dizer "isso aqui é um comprovante").
- **Valores** — previsto e realizado sugeridos, editáveis.
- Rodapé: *"Os arquivos não são copiados — o Financeiro passa a enxergar os mesmos anexos deste card."*

Se o card já estiver vinculado, o modal muda o título para **"Sincronizar com o Financeiro"** e mostra
quantos documentos novos serão vinculados.

### 6.4 Mapas de derivação

`AttachmentKind` → `FinanceDocumentKind`:

| Anexo do card | Documento do financeiro |
|---|---|
| `orcamento` | `orcamento` |
| `contrato` | `contrato` |
| `nota_fiscal` | `nota_fiscal` |
| `comprovante` | `comprovante` |
| `art` | `art` |
| `boleto` | `boleto` |
| `geral`, `minuta` | **não mapeados** — vão para "Outros anexos do card" no painel de documentos, com botão para classificar manualmente |

> **Por que `minuta` não vira `contrato` automaticamente:** minuta é a **proposta** do fornecedor
> (specs/19), não o contrato assinado. Promovê-la sozinha marcaria o controle "CONTRATO" como
> resolvido antes de existir contrato — exatamente o erro que a prestação de contas precisa evitar.

Derivação de `status` (só quando `status_auto = true`; qualquer edição manual grava `status_auto = false`):

```
tem contrato && tem nota_fiscal  → contrato_ok_nota_ok
tem contrato                     → contrato_ok_falta_nota
tem orcamento                    → aguardando_contrato
nenhum                           → orcamento
```

`nao_aplicado` **nunca** é atribuído automaticamente (é decisão humana) e, uma vez escolhido, congela
a derivação. Derivação de `art_status`: existe documento `art` → `art_ok`; caso contrário mantém o
valor atual (o sistema não sabe distinguir "não tem" de "aguardando envio").

### 6.5 De onde sai o realizado do card

O card já guarda `actual_value` e, desde a entrega de valores negociados, `valor_sem_nota`,
`valor_com_nota` e `negociado`. Regra de preenchimento de `unit_actual`:

```
negociado = com_nota  → valor_com_nota
negociado = sem_nota  → valor_sem_nota
senão                 → actual_value (pode ser null)
```

Só é aplicado **na criação** da linha ou quando o usuário pede "recalcular a partir do card" no modal
— sincronização automática de valor sobrescreveria ajuste feito no financeiro.

### 6.6 Exclusão de anexo vinculado

`CardController::destroyAttachment()` passa a checar `finance_documents`:

- Planilha **aberta**: exclui, mas o SweetAlert2 de confirmação avisa *"Este arquivo também é o
  COMPROVANTE da linha #123 do Financeiro do evento X e será removido de lá."* (o `cascadeOnDelete`
  cuida do vínculo).
- Planilha **fechada**: bloqueia (422) com a mensagem de que a prestação de contas do evento está
  fechada. Prova de pagamento de evento encerrado não pode sumir por um clique no Kanban.

## 7. Regras de cálculo (`app/Services/Finance/FinanceSummaryService.php`)

Tudo em SQL, uma consulta por bloco. Nada de laço em Blade.

**Custo previsto vigente** (a regra que concilia Previsto 1 e Previsto 2):

```sql
SUM(CASE WHEN unit_estimated_2 IS NULL THEN total_estimated_1 ELSE total_estimated_2 END)
```

Ou seja: a linha que já foi refinada usa o Previsto 2; a que não foi continua valendo pelo Previsto 1.
Quando `uses_second_estimate = false`, o vigente é sempre o Previsto 1 e a UI esconde o bloco.

| Indicador | Fórmula |
|---|---|
| Receita prevista | `SUM(finance_revenues.estimated_value)` |
| Receita realizada | `SUM(finance_revenues.actual_value)` |
| Recebido / Falta receber | `SUM(received_value)` / `SUM(pending_value)` |
| Custo previsto 1 | `SUM(total_estimated_1)` |
| Custo previsto 2 | `SUM(total_estimated_2)` (só com `uses_second_estimate`) |
| Custo previsto vigente | expressão acima |
| Custo realizado | `SUM(total_actual)` |
| **Resultado previsto** | Receita prevista − Custo previsto vigente |
| **Resultado realizado** | Receita realizada − Custo realizado |
| Pago | `SUM(finance_payments.amount)` (join pela planilha) |
| Falta pagar | Custo realizado − Pago |
| Custo por item | `GROUP BY fornecedor_categoria_id` sobre as três colunas de total |
| Pago por grupo | `GROUP BY finance_payment_source_id` |
| Acerto de sócios | Resultado realizado × `percentage` / 100 (quando `manual_amount = false`) |
| Desvio | Realizado − Previsto vigente; **% de realização** = Realizado / Previsto vigente |

Arredondamento: 2 casas, `DECIMAL(15,2)`, moeda BRL, máscara na UI (helper `Br::money()` já existe).
Divisões por zero devolvem `null` e a UI mostra `—`, nunca `0%`.

## 8. Telas e UX

Menu lateral, seção **Financeiro** (já existe): `Planejamento` é substituído por
**`Financeiro dos Eventos`** (`fa-file-invoice-dollar`), acima de `Banco de Preços`.

### 8.1 Lista de eventos (`/financeiro`)

Tabela paginada com busca e filtros server-side (padrão do projeto), uma linha por evento:
nome, data, status da planilha, receita prevista/realizada, custo previsto/realizado, **resultado**
(verde/laranja), % pago. Coordenador restrito por evento vê só os seus (specs/20).

### 8.2 Resumo Geral (`/financeiro/eventos/{evento}`)

Cabeçalho com **EVENTO / DATA / LOCAL** (vem de `events`, como as linhas A1:A3 do modelo) e o toggle
**"Usar Previsto 2"**.

- **Dois blocos lado a lado** — `RESUMO GERAL PREVISTO` e `RESUMO GERAL REALIZADO`, cada um com
  Receita, Custo e **Resultado** em destaque; abaixo do realizado, o desvio e o % de realização.
- **Custo por item** — barras horizontais por categoria (previsto x realizado), ordenadas por valor.
- **Andamento** — Pago / Falta pagar, com barra de progresso e quebra por grupo de pagamento.
- **Acerto de sócios** — tabela editável: sócio, porcentagem, total.
- **Alertas de consistência** (o que a planilha nunca avisou): linhas com realizado sem comprovante,
  linhas em `contrato_ok_falta_nota` a X dias, pagamentos acima do realizado, percentuais de sócios
  ≠ 100%. Cada alerta linka para a linha filtrada.

Gráficos em Chart.js ou SVG inline, paleta do design system, sem emoji.

### 8.3 Receitas (`/financeiro/eventos/{evento}/receitas`)

Grade editável inline espelhando a aba: Receita (categoria) · Descrição · Valor previsto · Valor
realizado · Recebido · Falta receber (calculado, somente leitura) · Recebido por · Obs. Rodapé
sticky com `TOTAL GERAL`. Botão **"Adicionar patrocínio"** cria linha `patrocinio` com foco direto na
descrição — é o caso que mais se repete.

### 8.4 Custos (`/financeiro/eventos/{evento}/custos`)

O centro do módulo. Grade tipo planilha, Alpine.js, com:

- **Colunas fixas** (congeladas à esquerda): ITEM (categoria) e DESCRIÇÃO. O resto rola na horizontal.
- **Grupos de coluna com toggle de visibilidade**: `Previsto 1` · `Previsto 2` (oculto quando
  desligado) · `Realizado` · `Pagamentos` · `Controle`. Sem isso, 26 colunas viram um borrão.
- **Edição inline com autosave por linha** (debounce ~600 ms, `PUT` da linha inteira), indicador de
  "salvando/salvo" e reversão visual em erro. Navegação por teclado: `Tab`/`Shift+Tab` entre células,
  `Enter` desce, `Esc` cancela a edição da célula.
- **Totais calculados na hora no cliente** (para feedback imediato) e **reconciliados pela resposta do
  servidor** — o número que vale é sempre o da coluna gerada.
- **Coluna CONTROLE**: seis chips (Font Awesome) — Orçamento, Contrato, NF, Comprovante, ART, Boleto.
  Cinza = ausente, verde = presente (com contador quando > 1). Clique abre o **painel de documentos**
  da linha (drawer lateral): lista dos arquivos, quem enviou, data, origem (`Card #123` ou `Upload
  direto`), botão de abrir (inline, com `nosniff`) e de anexar novo.
- **Badge de origem** na linha: `#123` clicável quando `card_id` está preenchido, levando ao card
  (rota de link direto da specs/18).
- **Filtros server-side**: categoria, status, ART, fornecedor, grupo de pagamento, "só com pendência
  documental", "só com falta pagar", busca por descrição.
- **Rodapé sticky** com os totais das colunas visíveis, sempre refletindo o filtro aplicado.
- **Adicionar linha** ao final da grade (padrão Pipefy do projeto) e **duplicar linha** (itens como
  `INFLUENCER` repetem N vezes no modelo).
- **Ações em massa**: mudar status, atribuir grupo de pagamento, excluir (com SweetAlert2).

### 8.5 Fechamento

Botão **"Fechar prestação de contas"** (coordenador/admin) com resumo do que ainda está pendente
(documentos faltando, falta pagar > 0) e confirmação SweetAlert2. Fechada, a planilha vira somente
leitura; **reabrir** é exclusivo de `admin` e fica registrado (`closed_at`/`closed_by` limpos e um
comentário no log da planilha).

## 9. Rotas (`routes/web.php`, dentro do grupo autenticado)

```
GET    financeiro                                        finance.index
GET    financeiro/eventos/{evento}                       finance.show            (Resumo Geral)
PUT    financeiro/eventos/{evento}/config                finance.config.update   (usa previsto 2, notas)
POST   financeiro/eventos/{evento}/fechar                finance.close
POST   financeiro/eventos/{evento}/reabrir               finance.reopen          (admin)

GET    financeiro/eventos/{evento}/receitas              finance.revenues.index
POST   financeiro/eventos/{evento}/receitas              finance.revenues.store
PUT    financeiro/receitas/{revenue}                     finance.revenues.update
DELETE financeiro/receitas/{revenue}                     finance.revenues.destroy

GET    financeiro/eventos/{evento}/custos                finance.costs.index
POST   financeiro/eventos/{evento}/custos                finance.costs.store
PUT    financeiro/custos/{item}                          finance.costs.update
DELETE financeiro/custos/{item}                          finance.costs.destroy
POST   financeiro/custos/{item}/duplicar                 finance.costs.duplicate
POST   financeiro/eventos/{evento}/custos/em-massa       finance.costs.bulk

POST   financeiro/custos/{item}/pagamentos               finance.payments.store
PUT    financeiro/pagamentos/{payment}                   finance.payments.update
DELETE financeiro/pagamentos/{payment}                   finance.payments.destroy

GET    financeiro/custos/{item}/documentos               finance.documents.index   (drawer, JSON)
POST   financeiro/custos/{item}/documentos               finance.documents.store   (upload direto)
POST   financeiro/custos/{item}/documentos/vincular      finance.documents.attach  (anexo de card)
GET    financeiro/documentos/{document}                  finance.documents.show    (inline/nosniff)
DELETE financeiro/documentos/{document}                  finance.documents.destroy

PUT    financeiro/eventos/{evento}/socios                finance.settlements.sync

GET    financeiro/eventos/{evento}/exportar              finance.export            (XLSX no layout do modelo)
POST   financeiro/eventos/{evento}/importar/preview      finance.import.preview
POST   financeiro/eventos/{evento}/importar              finance.import

POST   cards/{card}/financeiro                           cards.finance.sync        (a ponte, §6)
GET    cards/{card}/financeiro/preview                   cards.finance.preview     (dados do modal)

GET    financeiro/configuracoes/fontes                   finance.sources.index     (admin)
Resource financeiro/configuracoes/fontes                 finance.sources.*         (admin)
Resource financeiro/configuracoes/itens                  finance.presets.*         (admin/coordenador)
```

Model binding: `{evento}` → `Event` (a planilha é resolvida por `firstOrCreate` no controller, não na
rota). Todas as rotas de escrita passam por Policy (§11).

## 10. Camadas

```
app/Actions/Finance/
    SyncCardToFinance.php        ponte card → linha de custo + documentos (idempotente)
    CreateFinanceDocument.php    valida a invariante "attachment XOR upload", grava o arquivo
    DeriveCostItemStatus.php     status e ART a partir dos documentos (respeita status_auto)
    RegisterPayment.php          pagamento + revalidação de "falta pagar"
    CloseFinanceSheet.php        fechamento com checklist de pendências
    ImportFinanceSpreadsheet.php leitura do XLSX modelo → linhas (§12)

app/Services/Finance/
    FinanceSummaryService.php    todas as agregações do resumo (§7)
    FinanceSheetProvider.php     firstOrCreate + seed de receitas + eager loads padrão
    FinanceExportService.php     geração do XLSX no layout do modelo

app/Http/Controllers/Finance/   (FinanceSheetController, FinanceCostItemController,
                                 FinanceRevenueController, FinancePaymentController,
                                 FinanceDocumentController, FinanceImportExportController,
                                 FinancePaymentSourceController, FinanceItemPresetController)
app/Http/Requests/Finance/      (Store/Update por recurso — validação nunca no controller)
app/Policies/                   FinanceSheetPolicy, FinanceCostItemPolicy, FinanceDocumentPolicy
app/Observers/                  CardAttachmentObserver
app/Support/ServesStoredFile.php  trait com a resposta inline segura (extraída do CardController)
```

Dependência nova: **`phpoffice/phpspreadsheet`** (export §12 e import do modelo). Compatível com PHP
8.1; preferida a `maatwebsite/excel` por ser a única peça necessária, sem facade nem convenções extras.

## 11. Permissões

- **`admin`**: tudo, inclusive reabrir planilha fechada, catálogo de fontes de pagamento e presets.
- **`coordenador`**: cria/edita linhas, receitas, pagamentos e documentos; fecha a planilha.
  **Restrito por evento** (specs/20): só enxerga e edita planilhas dos eventos vinculados a ele —
  reusa `User::allowedEventIds()`, mesma mecânica do `Card::scopeVisibleTo()`.
- **`usuario`**: **sem acesso** ao módulo (nem no menu). Ele participa pelo Kanban — sobe o anexo no
  card, e o botão "Enviar para o Financeiro" fica disponível para quem edita o card, porque o efeito
  colateral é criar/atualizar uma linha, não ler o financeiro do evento.
- Planilha `fechado`: toda rota de escrita responde 422 (exceto reabrir), independente do papel.

## 12. Importação e exportação

**Exportação (`finance.export`)** — gera o XLSX **no layout do modelo** (abas `RESUMO GERAL`,
`RECEITAS`, `CUSTOS`, mesmas colunas e ordem), com valores já calculados. Serve para mandar à
contabilidade e para os sócios sem obrigar ninguém a entrar no sistema. É via de mão única: o arquivo
exportado **não** volta a ser fonte da verdade.

**Importação (`finance.import`)** — sobe um `FINANCEIRO - MODELO.xlsx` já preenchido de um evento em
andamento e converte em linhas do sistema. É a ferramenta de migração, essencial para a adoção (há
eventos em curso hoje na planilha).

- Lê `CUSTOS!A8:AA` e `RECEITAS!A4:G21`, ignorando linhas 100% vazias.
- Casa o ITEM com `fornecedor_categorias` por nome normalizado (sem acento, caixa alta); o que não
  casar cai em `Outros` e é sinalizado na pré-visualização.
- Converte STATUS e ART pelos rótulos exatos das listas do arquivo (§2).
- Distribui as colunas O–S (`CAIXA EVENTO`…`BAR`) em `finance_payments`, uma por valor > 0.
- Colunas V–AA (controle) do arquivo são apenas marcações textuais, **não** arquivos: viram uma
  observação por linha ("no arquivo original constava CONTRATO OK"), nunca `finance_documents` —
  documento sem arquivo seria uma prestação de contas falsa.
- **Pré-visualização obrigatória** antes de gravar (mesmo padrão da importação atual de planos),
  com contagem de linhas, avisos e possibilidade de descartar linhas.

## 13. Performance

- Agregações do resumo: **uma consulta por bloco** (`SUM`/`GROUP BY`), com os índices de §4.3. Uma
  planilha típica tem ~200 linhas de custo — sem cache, com folga.
- Grade de custos: paginação server-side de 100 linhas (com "carregar mais"), `with(['categoria',
  'fornecedor', 'documents' => fn ($q) => $q->select('id','finance_cost_item_id','kind')])` para o
  contador de chips sem N+1, e `withSum('payments as paid_total', 'amount')`.
- Autosave envia **só a linha alterada**; a resposta traz a linha recalculada (colunas geradas) e os
  totais do rodapé, evitando um segundo round-trip.
- Índice `finance_documents(finance_cost_item_id, kind)` cobre a montagem dos chips.

## 14. Destino do módulo antigo (specs/09)

O `Planejamento Financeiro` atual (`financial_plans` / `financial_entries`, telas
`/financeiro/planos` e `/financeiro/comparativo`) tem escopo diferente: agrupa por empresa e período,
sem evento, sem documentos, sem pagamentos. Estender aquela tabela exigiria ~20 colunas novas e ainda
assim não daria conta dos pagamentos nem do controle documental.

Decisão: **construir o módulo novo em paralelo e aposentar o antigo em seguida**, sem migração
destrutiva.

1. Entrega 1: módulo novo no ar; menu passa a apontar para ele.
2. Entrega 1: `/financeiro/planos` e `/financeiro/comparativo` continuam acessíveis por URL (fora do
   menu) com um aviso *"Módulo substituído pelo Financeiro do Evento"*, para consulta do histórico.
3. Entrega 2 (após o cliente confirmar que nada relevante ficou lá): rotas, controllers, views,
   `FinancialReportService`, models e tabelas removidos numa migration `down`-reversível.

Nenhuma linha de `financial_entries` é migrada automaticamente: os dados de lá não têm evento nem
fornecedor estruturado, e adivinhar o vínculo produziria uma prestação de contas errada.

## 15. Testes (`tests/Feature/Finance/`, PHPUnit — sem front/e2e/carga)

- `FinanceSheetTest` — criação sob demanda por evento; seed das receitas padrão; fechamento bloqueia
  escrita; reabrir só admin.
- `FinanceCostItemTest` — colunas geradas conferem (`total = unit × qty × diárias`) nos três cenários;
  `unit_actual = null` não conta como realizado 0 no resumo.
- `SyncCardToFinanceTest` — cria linha a partir do card; **idempotência** (rodar 2×, 1 linha e 1
  documento); card sem evento → 422; anexo novo em card vinculado vira documento (observer); `geral`
  e `minuta` **não** viram documento automaticamente.
- `FinanceDocumentTest` — invariante attachment XOR upload; resposta inline com `nosniff` e
  `Content-Type` do conteúdo; exclusão de anexo bloqueada com planilha fechada.
- `FinancePaymentTest` — pago/falta pagar; pagamento parcial por múltiplas fontes; alerta de
  pagamento acima do realizado.
- `FinanceSummaryTest` — resultado previsto/realizado; regra do Previsto 2 (linha sem previsto 2 usa o
  previsto 1); custo por categoria; acerto de sócios com e sem `manual_amount`.
- `FinanceAccessTest` — `usuario` recebe 403; coordenador restrito enxerga só os eventos dele
  (specs/20); planilha fechada responde 422 nas rotas de escrita.
- `FinanceImportTest` — importa o próprio `referencia/FINANCEIRO - MODELO (1).xlsx`, confere contagem
  de linhas, mapeamento de categorias e ausência de `finance_documents`.

## 16. Critérios de aceite

- [ ] Cada evento tem uma planilha financeira no sistema, criada sob demanda, com Resumo, Receitas e
      Custos equivalentes às abas do arquivo modelo.
- [ ] O botão **"Enviar para o Financeiro"** no card cria/atualiza a linha de custo do evento e
      **vincula os anexos do card** como documentos, sem reupload e sem duplicar arquivo.
- [ ] Card movido para um quadro com `feeds_finance` sincroniza sozinho; anexo novo em card já
      vinculado aparece no financeiro sem ação manual.
- [ ] Reenviar o mesmo card não duplica linha nem documento.
- [ ] Linha de custo tem ITEM (categoria reaproveitada de `fornecedor_categorias`), DESCRIÇÃO com
      autocomplete dos 168 itens do modelo, STATUS e ART com as opções exatas do arquivo, empresa,
      autorizado por, diárias, quantidade e os três cenários de valor.
- [ ] `TOTAL = unitário × quantidade × diárias` é garantido pelo banco nos três cenários; Previsto 2
      pode ser ligado/desligado por evento e a linha sem Previsto 2 continua valendo pelo Previsto 1.
- [ ] Pagamentos por grupo (Caixa do Evento, Sócios, Ticketeira, Bar, …) com pagamento parcial, e
      PAGO / FALTA PAGAR calculados.
- [ ] Controle documental com os seis tipos (Orçamento, Contrato, NF, Comprovante, ART, Boleto),
      visível como chips na grade e abrindo o arquivo em nova aba.
- [ ] Receitas com descrição por linha (patrocínio identificado), previsto, realizado, recebido,
      falta receber e recebido por.
- [ ] Resumo Geral com Receita/Custo/Resultado **previsto e realizado**, custo por categoria,
      andamento (pago/falta pagar) e acerto de sócios.
- [ ] Todas as agregações vêm do banco (`SUM`/`GROUP BY`), sem N+1 e sem cálculo em Blade.
- [ ] Export XLSX no layout do modelo e import de planilha preenchida com pré-visualização.
- [ ] Permissões: `usuario` sem acesso; coordenador restrito por evento; planilha fechada é somente
      leitura.
- [ ] Font Awesome, SweetAlert2, preto + `#ff8c1e`, zero emoji.
- [ ] Suíte `tests/Feature/Finance` verde; `pint --dirty` e `npm run build` limpos.

## 17. Fora de escopo

- Aba `Página5` do modelo (agenda semanal) — o acompanhamento de tarefas é o Kanban.
- Conciliação bancária automática, integração com OFX/extrato, emissão de boleto ou NF.
- Rateio contábil, centro de custo e DRE por empresa do grupo.
- Fluxo de caixa projetado por data (o modelo não tem; entra como evolução se pedido).
- Testes de automação de front, carga ou Playwright (regra do projeto).

---

## Apêndice A — Catálogo de itens do modelo

168 descrições em 12 categorias, extraídas de `CUSTOS!A8:B183`. Fonte do seed de
`finance_item_presets`: [`specs/data/financeiro-itens-modelo.csv`](data/financeiro-itens-modelo.csv).

| Categoria | Itens |
|---|---:|
| CAMARIM | 4 |
| CENOGRAFIA | 9 |
| DIVULGAÇÃO | 15 |
| ESTRUTURA GERAL | 28 |
| ESTRUTURA PALCO | 7 |
| LICENÇAS E TAXAS | 21 |
| LOGÍSTICA | 3 |
| MÍDIA | 9 |
| OUTROS | 9 |
| PROJETO | 6 |
| RH | 31 |
| SERVIÇOS | 26 |

A lista de categorias do dropdown do arquivo é maior que a das linhas preenchidas (inclui `BAR`,
`ARTÍSTICO`, `ESTRUTURA CAM EMPRESARIAL`, `RODEIO`, `ESTRUTURA CAMAROTE`, `PREFEITURA`, sem itens
associados) — todas entram em `fornecedor_categorias` (§4.9), mesmo sem preset.

## Apêndice B — Mapa planilha → sistema

**CUSTOS**

| Col. | Cabeçalho | Campo |
|---|---|---|
| A | ITEM | `finance_cost_items.fornecedor_categoria_id` |
| B | DESCRIÇÃO | `description` |
| C | STATUS | `status` (`FinanceCostStatus`) |
| D | ART | `art_status` (`FinanceArtStatus`) |
| E | EMPRESA | `fornecedor_id` / `supplier_name` |
| F | AUTORIZADO POR | `authorized_by` / `authorized_by_name` |
| G | DIÁRIAS | `daily_count` |
| H | QUANT. | `quantity` |
| I / J | VALOR UNIT. / TOTAL PREVISTO (1) | `unit_estimated_1` / `total_estimated_1` (gerada) |
| K / L | VALOR UNIT. / TOTAL PREVISTO (2) | `unit_estimated_2` / `total_estimated_2` (gerada) |
| M / N | VALOR UNIT. / TOTAL REALIZADO | `unit_actual` / `total_actual` (gerada) |
| O–S | CAIXA EVENTO, SÓCIO 1, SÓCIO 2, TICKETEIRA, BAR | `finance_payments` + `finance_payment_sources` |
| T | PAGO | `SUM(finance_payments.amount)` |
| U | FALTA PAGAR | `total_actual − PAGO` |
| V–AA | ORÇAMENTO, CONTRATO, NOTA FISCAL, COMPROVANTE, ART, BOLETO | `finance_documents.kind` |

**RECEITAS**

| Col. | Cabeçalho | Campo |
|---|---|---|
| A | RECEITA | `finance_revenues.category` (+ `description`) |
| B | VALOR PREVISTO | `estimated_value` |
| C | VALOR REALIZADO | `actual_value` |
| D | RECEBIDO | `received_value` |
| E | FALTA RECEBER | `pending_value` (gerada) |
| F | RECEBIDO POR | `finance_payment_source_id` |
| G | OBS | `notes` |

**RESUMO GERAL** — todo calculado por `FinanceSummaryService` (§7); nenhuma célula tem armazenamento
próprio, ao contrário do arquivo, onde as fórmulas apontam para intervalos fixos (`CUSTOS!F214`,
`CUSTOS!G15`, …) que quebram quando alguém insere uma linha. É a principal fonte de erro silencioso
da planilha atual e some por construção.
