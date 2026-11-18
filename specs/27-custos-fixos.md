# 27 — Custos fixos

Cadastro de **custos fixos recorrentes** da empresa (aluguel, água, parcelamentos, empréstimos) que gera
sozinho um **card por parcela** num **quadro de custos fixos**, com o selo **"Parcela X de Y"**.

Definido em 18/11/2026, em sessão de perguntas e respostas com o usuário.

**Exemplos de custos:**

| Custo | Parcelas |
|---|---|
| Aluguel | 12 |
| Água | 12 |
| Parcelamento | 8 |
| Empréstimo | 5 |

**Premissas:**
- Custo fixo é da **empresa**, não de um evento. Os cards dele nunca têm evento e não aparecem misturados
  com os quadros dos eventos.
- **Nesta etapa nada vai para o Financeiro.** Os cards vivem só no Kanban. Não há ponte com o
  Financeiro do Evento (specs/23) nem com o Planejamento Financeiro (specs/09).
- Reaproveita todo o Kanban existente (colunas, painel do card, anexos, comentários, aprovação,
  histórico). O que é novo: o cadastro, o gerador de parcelas, o tipo de quadro e o selo.

---

## 1. Quadro de custos fixos

### 1.1 Tipo do quadro

- Coluna nova `boards.fixed_costs` (boolean, padrão `false`), com cast no model `Board`.
- **Configuração**: em `/quadros/{board}/configurar` (`boards/config.blade.php`), um bloco novo
  **"Quadro de custos fixos"** abaixo do bloco "Formulário do fornecedor". O checkbox
  "Este quadro recebe custos fixos" salva via `PUT quadros/{board}/config-custos-fixos`
  (`boards.fixed-costs.update`), no mesmo padrão de `boards.supplier-form.update`, com
  feedback por toast do SweetAlert2.
- **Exclusão mútua com o Financeiro**: o quadro não pode ter `fixed_costs` e `feeds_finance`
  ligados ao mesmo tempo. A validação fica no Form Request e retorna:
  "Um quadro de custos fixos não pode alimentar o Financeiro."
- **Desligar a chave** é bloqueado enquanto existir custo fixo (não excluído) apontando para o quadro:
  "N custo(s) fixo(s) geram cards neste quadro. Troque o quadro de destino deles antes."
- **Migration de dados**: ao criar a coluna, liga `fixed_costs = true` no quadro já existente de nome
  **"Custos Fixos"** (hoje o id 5), se ele existir. Procure pelo nome, nunca pelo id.

### 1.2 Menu e visibilidade

- O quadro continua no menu lateral **junto com os outros quadros e processos**, sem grupo próprio. O
  ícone e a cor são os configurados no quadro.
- **Quem vê o quadro**: só **Admin** e **Coordenador sem eventos vinculados** (`! isEventScoped()`).
  - `User::canAccessBoard()`: num quadro com `fixed_costs`, retorna `true` só para esses perfis. Um
    Usuário comum não entra, mesmo vinculado ao quadro em `user_board`.
  - `User::isAssignedOnBoard()` não abre exceção para quadros de custos fixos (`BoardPolicy::view`
    nega).
  - O select de responsável dos cards desses quadros lista só Admins e Coordenadores sem eventos.
  - `BoardController::index` e a consulta `$menuBoards` do `sidebar.blade.php` escondem esses quadros
    de quem não pode vê-los.
  - Na configuração de acesso do quadro (usuários vinculados), o bloco fica desabilitado com a nota
    "Quadros de custos fixos são visíveis só para Admin e Coordenador sem eventos vinculados."
- Coordenador restrito por evento (specs/20): já não veria os cards, que não têm evento. A regra acima
  esconde também o quadro, para não aparecer um quadro vazio.

### 1.3 Separação dos dois mundos

- **Transferência** (`TransferCard` / `cards.transfer`): a lista de quadros de destino só mostra quadros
  do **mesmo tipo** (`fixed_costs` igual ao do quadro de origem). A Action também valida e lança
  `ValidationException` com "Cards de custos fixos não podem ir para quadros de eventos." (e a mensagem
  inversa).
- **Templates de cards** (specs/08): a importação de template num quadro de custos fixos fica fora do
  escopo. O botão não aparece nesses quadros.
- **Formulário externo** (specs/11) e **captura rápida** (specs/16): não podem ter um quadro de custos
  fixos como destino. As listas de quadros desses módulos filtram `fixed_costs = false`.

### 1.4 Painel do card num quadro de custos fixos

Vale para todos os cards do quadro, gerados ou avulsos:

- **Escondidos**: campo **Evento** e botão **"Enviar para o Financeiro"** (e o modal dele).
- **Empresa obrigatória**: `StoreCardRequest` / `UpdateCardRequest` exigem `empresa_id` quando o quadro
  tem `fixed_costs`, e proíbem `event_id` (devem chegar nulos).
- **Cards avulsos** (criados à mão) são permitidos e aparecem como card normal, sem selo.
- Os outros campos (fornecedor, responsável, prazo, valores, prioridade, cor, campos configuráveis,
  anexos, comentários) continuam como em qualquer quadro.

---

## 2. Cadastro de custos fixos

### 2.1 Campos

| Campo | Regra |
|---|---|
| Nome | Obrigatório, até 120 caracteres. Vira o título de cada card gerado (ex.: "Aluguel") |
| Empresa | Obrigatória, uma por custo. Vem de `/empresas`: ativas, mais a já vinculada mesmo se inativa |
| Valor da parcela | Obrigatório, decimal(15,2) maior que zero. Vira o **valor previsto** (`estimated_value`) do card |
| Quantidade de parcelas | Obrigatória, inteiro de 1 a 360 |
| Começar na parcela | Opcional, padrão 1. Inteiro de 1 até a quantidade. Serve para custo já em andamento |
| Primeiro vencimento | Obrigatório, data completa (ex.: 10/12/2026). É o vencimento da parcela "Começar na parcela" |
| Quadro de destino | Obrigatório. Só quadros **ativos** com `fixed_costs = true` |
| Coluna de destino | Opcional. Colunas do quadro escolhido. Vazio = coluna de entrada (`is_entry`) ou a primeira |
| Modo de geração | Obrigatório: **"Gerar todas as parcelas agora"** ou **"Gerar cada parcela X dias antes do vencimento"** |
| Dias de antecedência (X) | Obrigatório só no segundo modo. Inteiro de 0 a 60, padrão 10 |
| Descrição | Opcional, até 2000 caracteres. Copiada para a descrição de cada card |
| Ativo | Padrão: sim |

- **Ordem de dependência**: sem empresa ativa, o formulário aponta para o cadastro de empresas. Sem
  quadro de custos fixos, aponta para a configuração de quadros: "Marque um quadro como 'Quadro de
  custos fixos' antes de cadastrar."
- **Coluna** muda com o quadro (select dependente, Alpine). Coluna de outro quadro é rejeitada no
  Form Request.
- **Periodicidade**: só **mensal** nesta etapa.

### 2.2 Vencimentos

- A parcela `n` vence no **mesmo dia** do primeiro vencimento, `n − começar_na_parcela` meses depois.
- Quando o mês não tem esse dia, a parcela vence no **último dia do mês**. Exemplo: primeiro vencimento
  em 31/01 gera 28/02 (ou 29/02), 31/03, 30/04… A conta sempre parte do dia original, nunca do mês
  anterior (usar `addMonthsNoOverflow` a partir do primeiro vencimento).
- O vencimento vira o `due_date` do card e é a base do "X dias antes".
- A fórmula só monta o cronograma (cadastro, renovação, aumento do total). Depois disso, o vencimento
  de cada parcela é o que está gravado na linha dela (§2.3): uma exclusão (§5.5) renumera as seguintes
  sem mexer nos vencimentos, e a partir daí o número não diz mais o mês.

### 2.3 Tabelas

```php
Schema::create('fixed_costs', function (Blueprint $t) {
    $t->id();
    $t->foreignId('empresa_id')->constrained('empresas');
    $t->foreignId('board_id')->constrained('boards');
    $t->foreignId('board_column_id')->nullable()->constrained('board_columns')->nullOnDelete();
    $t->string('name', 120);
    $t->text('description')->nullable();
    $t->decimal('installment_value', 15, 2);
    $t->unsignedSmallInteger('installments_total');   // "de Y"
    $t->unsignedSmallInteger('start_installment')->default(1);
    $t->date('first_due_date');                        // vencimento da start_installment
    $t->string('generation_mode', 20);                 // FixedCostGenerationMode
    $t->unsignedTinyInteger('lead_days')->nullable();  // X, só no modo antecedência
    $t->boolean('active')->default(true);
    $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $t->timestamps();
    $t->softDeletes();
    $t->index(['active', 'generation_mode']);
});

Schema::create('fixed_cost_installments', function (Blueprint $t) {
    $t->id();
    $t->foreignId('fixed_cost_id')->constrained('fixed_costs')->cascadeOnDelete();
    $t->unsignedSmallInteger('number');                // "Parcela X"
    $t->date('due_date');
    $t->foreignId('card_id')->nullable()->constrained('cards')->nullOnDelete();
    $t->timestamp('generated_at')->nullable();                   // null = parcela ainda sem card
    $t->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete(); // null = comando agendado
    $t->timestamps();
    $t->unique(['fixed_cost_id', 'number']);
});

Schema::table('cards', fn (Blueprint $t) =>
    $t->foreignId('fixed_cost_installment_id')->nullable()->after('event_id')
      ->constrained('fixed_cost_installments')->nullOnDelete());

Schema::table('boards', fn (Blueprint $t) =>
    $t->boolean('fixed_costs')->default(false)->after('feeds_finance'));
```

- **Models**: `FixedCost` (soft delete; `empresa`, `board`, `column`, `installments`) e
  `FixedCostInstallment` (`fixedCost` com `withTrashed()`, `card` com `withTrashed()`).
  `Card::fixedCostInstallment()` (belongsTo).
- **Enums** (`app/Domain/Enums`):
  - `FixedCostGenerationMode`: `all_upfront` ("Gerar todas as parcelas agora") e `lead_days`
    ("Gerar cada parcela X dias antes do vencimento").
  - `CardOrigin`: novo caso `FixedCost = 'custo_fixo'`, rótulo "Custo fixo".
- **`fixed_cost_installments` é o cronograma completo**: uma linha por parcela, de "começar na parcela"
  até o total, cada uma com o seu vencimento. `generated_at` nulo = ainda sem card. Mantido pela
  `SyncFixedCostSchedule` ao cadastrar, editar e renovar: total maior acrescenta parcelas no fim (mês a
  mês depois da última); total menor remove do fim as ainda não geradas; mudar início ou primeiro
  vencimento (só possível sem nada gerado) refaz tudo. A migration `2026_11_18_000005` completou o
  cronograma dos custos que já existiam.
- **Sem duplicar**: a geração "reserva" a parcela com um UPDATE condicional (`generated_at` ainda nulo)
  antes de criar o card. Com o comando e o botão rodando juntos, o segundo encontra a parcela já
  reservada e pula. O índice único `(fixed_cost_id, number)` continua garantindo um número por custo.
- O total "de Y" **não é copiado** para o card. O selo lê `fixed_costs.installments_total` na hora de
  exibir, então uma renovação atualiza todos os selos.

### 2.4 Acesso e menu

- `FixedCostPolicy`: Admin e Coordenador **sem eventos vinculados** (a mesma régua de
  `CostCenterPolicy`).
- Menu **Cadastros → Custos fixos**, logo após Contas bancárias, em `/custos-fixos`
  (`fixed-costs.*`). Ícone `fa-solid fa-arrows-rotate`.

### 2.5 Listagem

- **Colunas**:
  - Custo fixo (nome e, abaixo, o quadro de destino)
  - Empresa
  - Valor da parcela
  - Progresso: "4 de 12 geradas" e uma barra
  - Próximo vencimento (o da próxima parcela **não gerada**, ou "Todas geradas")
  - Modo ("Todas agora" ou "10 dias antes")
  - Status (Ativo ou Inativo)
  - Ações (ver, editar, gerar próxima, renovar, inativar, excluir)
- **Busca** por nome. **Filtros**: empresa (select simples, como em Contas bancárias, com as empresas que
  têm custo fixo), quadro, modo e status.
- Paginação de 15. Ordenação padrão: próximo vencimento crescente, com os "todas geradas" no fim.

### 2.6 Detalhe do custo fixo

`/custos-fixos/{fixedCost}` mostra os dados do cadastro e a **lista de parcelas**, de 1 a Y:

| Parcela | Vencimento | Situação | Card | Ações |
|---|---|---|---|---|
| 1 de 12 | 10/12/2026 | Paga · coluna "Concluído" | link `#123` | (excluir desabilitado) |
| 2 de 12 | 10/01/2027 | Card excluído | — | Gerar card novamente · Excluir |
| 3 de 12 | 10/02/2027 | Será gerada em 31/01/2027 | — | Excluir |
| … | | | | |

**Situações possíveis:**
- **Anterior ao início**: parcelas abaixo de "Começar na parcela". Aparecem esmaecidas: "Paga fora do
  sistema".
- **Paga**: card concluído ou na etapa final do quadro (coluna marcada como final, ex.: "Concluído").
- **Gerada**: mostra a coluna atual do card, ou "Arquivado".
- **Card excluído**: a parcela foi gerada e o card excluído depois. A geração automática não recria;
  o botão "Gerar card novamente" cria um card novo (§3.4).
- **Prevista**: mostra "Será gerada em dd/mm/aaaa" ou, no modo "todas agora", "Pendente".
- **Pausada**: custo inativo.

Links do card usam a URL direta `/quadros/{board}/card/{card}` (specs/18).

---

## 3. Geração das parcelas

### 3.1 Action única

`App\Actions\FixedCosts\GenerateFixedCostInstallments::execute(FixedCost $cost, ?User $actor, ?CarbonImmutable $today = null, bool $onlyNext = false): int`

1. Não faz nada se o custo estiver inativo ou excluído.
2. Pega as parcelas **pendentes** do cronograma (`generated_at` nulo), em ordem de número.
3. Escolhe as que devem nascer agora:
   - Modo `all_upfront`: todas as pendentes.
   - Modo `lead_days`: as pendentes cujo `due_date − lead_days <= hoje`. Inclui as atrasadas: um
     vencimento que já passou ainda gera o card, para a conta não sumir.
   - `onlyNext = true` (botão "Gerar próxima parcela agora"): só a menor pendente, ignorando a janela.
4. Para cada uma, numa transação: reserva a linha (grava `generated_at` só se ainda estiver nulo) e
   chama a `CreateCard::execute()` já existente com estes dados:
   - `board_column_id`: a coluna do custo, ou nulo (a `CreateCard` resolve a de entrada)
   - `title`: nome do custo
   - `description`: descrição do custo
   - `empresa_id`: empresa do custo
   - `estimated_value`: valor da parcela
   - `due_date`: vencimento
   - `origin`: `CardOrigin::FixedCost`

   Depois grava `fixed_cost_installment_id` no card e `card_id` na linha. Sem fornecedor no card, nada
   vai para o Banco de Preços.
5. Se a reserva não pegar nenhuma linha (outro processo gerou antes), pula a parcela em silêncio.
6. Retorna quantos cards criou.

**Quem chama a Action:**
- Salvar o cadastro (criar, ou editar trocando o modo para "todas agora"). No modo `lead_days`, as
  parcelas que já estão dentro da janela nascem na hora.
- Renovar.
- Reativar.
- O comando agendado.
- O botão "Gerar próxima parcela agora".

### 3.2 Comando agendado

- `php artisan custos-fixos:gerar` (`App\Console\Commands\GenerateFixedCosts`), com a opção
  `--date=AAAA-MM-DD` para simular outro dia.
- Percorre os custos **ativos** no modo `lead_days` que ainda têm parcela pendente, em lotes
  (`chunkById`), e chama a Action para cada um. No fim, imprime quantos cards foram gerados.
- Registro em `App\Console\Kernel::schedule`:
  `$schedule->command('custos-fixos:gerar')->dailyAt('06:00')->withoutOverlapping();`
- **Cron no servidor** (pré-requisito do modo `lead_days`). Uma linha no crontab do usuário do PHP:
  ```
  * * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
  ```
  Sem essa linha, o modo "X dias antes" só gera parcelas pelo botão (§3.3) ou pelo comando à mão.

### 3.3 Botão "Gerar próxima parcela agora"

- Disponível na listagem e no detalhe quando o custo está ativo e tem parcela pendente.
- Pede confirmação com o SweetAlert2: "Gerar agora a parcela 5 de 12 (vence em 10/04/2027)?". Gera só
  a próxima, mesmo fora da janela.
- Serve de reserva quando o cron falha e para adiantar uma conta.

### 3.4 Card de parcela excluído ou arquivado

- **A geração automática não recria.** A parcela continua gerada e o detalhe mostra "Card excluído".
- **"Gerar card novamente"** (detalhe do custo, na linha da parcela; `RegenerateInstallmentCard`):
  pede confirmação no SweetAlert2 e cria um card novo para a parcela, com os dados atuais do cadastro e
  o vencimento da parcela. O card antigo, na lixeira, deixa de apontar para a parcela. Funciona mesmo
  com o custo inativo (é um pedido explícito), mas exige que o quadro de destino ainda seja de custos
  fixos. Com card ativo, recusa: "A parcela N já tem um card ativo."
- Arquivar, concluir e mover o card não afetam a geração das próximas parcelas.

---

## 4. Selo "Parcela X de Y"

- **No Kanban** (card na coluna): selo no topo do card, acima do título, com fundo preto `#000000`,
  texto branco, ícone `fa-solid fa-arrows-rotate` e o texto **"Parcela 4 de 12"**. Usa o mesmo raio e
  o mesmo tamanho de fonte dos demais selos do card. **Sem laranja**: o accent é reservado a CTAs e
  estados ativos.
- **No painel do card**: o mesmo selo ao lado do `#ID`, com o link **"Ver custo fixo"**
  (`fa-solid fa-arrow-up-right-from-square`) para `/custos-fixos/{id}`. O link só aparece para quem
  pode ver o cadastro.
- **Cálculo**: X vem de `fixed_cost_installments.number` e Y de `fixed_costs.installments_total`.
  Para não gerar N+1, `kanbanData` e o painel carregam `fixedCostInstallment.fixedCost:id,installments_total`.
- Custo fixo excluído depois (só possível sem parcelas, §5.3): não se aplica. Custo inativo: o selo
  continua igual.
- O título do card é só o nome ("Aluguel"), porque o número da parcela está no selo.

### 4.1 Filtros do quadro de custos fixos

Na barra de filtros do quadro (server-side, como os demais filtros do Kanban):
- **Empresa**: já existe para todos os quadros e continua igual.
- **Tipo** (novo, só nos quadros com `fixed_costs`): Todos, Parcelas de custo fixo
  (`origin = custo_fixo`) ou Avulsos.

---

## 5. Editar, inativar, excluir e renovar

### 5.1 Editar

- **Nome, descrição, empresa e valor da parcela**: a mudança vale para as parcelas **ainda não
  geradas**. O formulário traz o checkbox **"Aplicar também às parcelas já geradas e não concluídas"**,
  desmarcado por padrão. Marcado, atualiza título, descrição, empresa e `estimated_value` dos cards
  gerados que não estão concluídos nem arquivados nem excluídos. A atualização é direta nesses quatro
  campos: a `UpdateCard` reescreve o card inteiro a partir do payload e apagaria os demais campos.
- **Quantidade**: não pode ficar abaixo do maior número de parcela já gerado: "Já existem parcelas
  geradas até a 6. A quantidade mínima é 6."
- **Começar na parcela e primeiro vencimento**: **travados** depois da primeira parcela gerada (campos
  desabilitados com tooltip explicando).
- **Quadro e coluna de destino**: podem mudar e valem só para as próximas parcelas. Os cards já gerados
  ficam onde estão.
- **Modo e dias de antecedência**: podem mudar. Trocar para "todas agora" gera as pendentes na hora,
  depois de confirmação no SweetAlert2 ("Isso vai criar N cards agora.").

### 5.2 Inativar e reativar

- **Inativar** pausa a geração. Os cards já gerados não mudam.
- **Reativar** chama a Action: no modo `lead_days`, as parcelas que já entraram na janela (inclusive as
  atrasadas) nascem na hora. Antes, um SweetAlert2 avisa quantas serão criadas.

### 5.3 Excluir

- **Com parcela gerada** (alguma linha em `fixed_cost_installments`): bloqueado com "Este custo fixo
  já gerou N parcela(s). Inative em vez de excluir." Na listagem, o botão aparece desabilitado com a
  explicação no tooltip.
- **Sem parcela gerada**: soft delete.

### 5.4 Renovar

- Ação **"Renovar"**, disponível quando todas as parcelas foram geradas (ou a qualquer momento, para
  estender). Um SweetAlert2 com campo numérico pergunta "Quantas parcelas a mais?" (1 a 360).
- Soma ao total: 12 + 12 = 24. A próxima parcela é a 13, com vencimento no mês seguinte ao da 12.
  Todos os selos passam a mostrar "de 24".
- Depois de somar, chama a Action (no modo "todas agora", nascem as 12 novas).
- **Encerrar antes do previsto** não precisa de ação própria: basta inativar.

### 5.5 Excluir parcela

- Botão de lixeira em cada parcela do detalhe do custo (`DeleteFixedCostInstallment`), com modal de
  confirmação do SweetAlert2: "Excluir a parcela 4 de 12 (vence em 30/04/2027)? O card #123 vai para a
  lixeira. As parcelas seguintes serão renumeradas (a 5 vira a 4) e mantêm os vencimentos. O total
  passa para 11."
- **Renumera as seguintes**: a 5 vira a 4, a 6 vira a 5… e o total cai 1. **Os vencimentos não mudam**
  (a de maio continua em maio); os selos dos cards já gerados acompanham o número novo.
- **O card da parcela**, se ativo, vai para a lixeira junto.
- **Bloqueios**, com o botão desabilitado e tooltip explicando:
  - **Parcela paga** (card concluído ou na etapa final do quadro): "A parcela N já foi paga e não pode
    ser excluída."
  - **Única parcela**: "Esta é a única parcela. Inative ou exclua o custo fixo."
- Parcelas anteriores a "começar na parcela" ("Paga fora do sistema") não têm linha e não são excluíveis.
- Renovar depois de excluir continua do mês seguinte à última parcela.

---

## 6. Rotas

```php
Route::middleware(['auth'])->group(function () {
    Route::resource('custos-fixos', FixedCostController::class)
        ->parameters(['custos-fixos' => 'fixedCost'])
        ->names('fixed-costs');
    Route::post('custos-fixos/{fixedCost}/gerar-proxima', [FixedCostController::class, 'generateNext'])
        ->name('fixed-costs.generate-next');
    Route::post('custos-fixos/{fixedCost}/renovar', [FixedCostController::class, 'renew'])
        ->name('fixed-costs.renew');
    Route::patch('custos-fixos/{fixedCost}/ativo', [FixedCostController::class, 'toggleActive'])
        ->name('fixed-costs.toggle-active');
    Route::scopeBindings()->group(function () {   // a parcela precisa ser do custo da URL (senão 404)
        Route::delete('custos-fixos/{fixedCost}/parcelas/{installment}', [FixedCostController::class, 'destroyInstallment'])
            ->name('fixed-costs.installments.destroy');
        Route::post('custos-fixos/{fixedCost}/parcelas/{installment}/gerar-card', [FixedCostController::class, 'regenerateInstallment'])
            ->name('fixed-costs.installments.regenerate');
    });

    Route::put('quadros/{board}/config-custos-fixos', [BoardController::class, 'updateFixedCosts'])
        ->name('boards.fixed-costs.update');
});
```

- O select dependente de coluna não usa rota: o formulário recebe as colunas de todos os quadros de
  custos fixos ativos (são poucas) e o Alpine filtra pelo quadro escolhido.
- As rotas do cadastro ficam no grupo `role:admin,coordenador`, como Centros de custo e Contas
  bancárias; a `FixedCostPolicy` barra o coordenador restrito por evento.
- Form Requests: `StoreFixedCostRequest`, `UpdateFixedCostRequest`, `RenewFixedCostRequest`,
  `UpdateBoardFixedCostsRequest`. Nenhuma validação no controller.
- Regras de negócio em Actions (`app/Actions/FixedCosts`): `CreateFixedCost`, `SyncFixedCostSchedule`,
  `GenerateFixedCostInstallments`, `UpdateFixedCost` (com a propagação opcional), `RenewFixedCost`,
  `ToggleFixedCost`, `DeleteFixedCostInstallment` e `RegenerateInstallmentCard`.
- O cálculo de vencimentos fica no model: `FixedCost::dueDateForOffset()` monta o cronograma e
  `FixedCost::dueDateFor()` lê o vencimento gravado da parcela.

### 6.1 Decisões tomadas na implementação

- **Excluir quadro** com custo fixo apontando para ele é bloqueado, com a mesma mensagem de desligar a chave.
- **Valor na face do card**: enquanto o card de parcela não tem valor real, a face mostra
  "Previsto: R$ X" (o valor da parcela), em vez de nada.
- **Lista de usuários**: o vínculo de quadros no cadastro de usuário não lista os quadros de custos fixos,
  porque o vínculo não dá acesso a eles.
- **Formulário externo já existente** num quadro que vira de custos fixos para de responder (link
  público some), e a tela de gestão do formulário redireciona com aviso.
- **Templates**: além do botão escondido, os Form Requests de template e a importação recusam quadro
  de custos fixos.
- **Comando com data inválida** (`--date` fora de AAAA-MM-DD) termina com erro e não gera nada.

---

## 7. Fora do escopo desta etapa

- Envio das parcelas ao Financeiro do Evento, ao Planejamento Financeiro ou a qualquer planilha.
- Centro de custo e conta bancária no custo fixo.
- Periodicidades além da mensal (semanal, trimestral, anual).
- Custo sem número de parcelas definido ("sem fim"). O caminho é renovar.
- Visão consolidada de despesas fixas por mês (previsto x pago).
- Fornecedor, responsável e evento no cadastro do custo fixo.
- Notificações de vencimento.
- Templates de cards nos quadros de custos fixos.

---

## 8. Critérios de aceite

- [x] Admin liga "Quadro de custos fixos" na configuração de um quadro. Ligar junto com `feeds_finance`
  é recusado. Desligar com custos fixos apontando para o quadro é recusado.
- [x] A migration marca o quadro "Custos Fixos" já existente.
- [x] Usuário comum e coordenador restrito por evento não veem o quadro de custos fixos no menu nem
  pela URL, nem o cadastro.
- [x] Cadastro "Aluguel, 12 parcelas, 1º vencimento 31/01/2027, todas agora" cria 12 cards na coluna
  escolhida, com vencimentos 31/01, 28/02, 31/03, 30/04… e selos "Parcela 1 de 12" a "Parcela 12 de 12".
- [x] Cadastro "Empréstimo, 5 parcelas, começar na 3, X = 10" cria só a parcela 3 quando o vencimento
  está a 10 dias ou menos. As parcelas 1 e 2 aparecem como "Paga fora do sistema".
- [x] `custos-fixos:gerar --date=...` gera as parcelas que entraram na janela, inclusive as atrasadas,
  e rodar de novo não duplica nada.
- [x] "Gerar próxima parcela agora" cria só a próxima, mesmo fora da janela.
- [x] Card de parcela excluído não é recriado pelo comando, e "Gerar card novamente" cria um card novo.
- [x] Excluir a parcela 2 de 5 renumera as seguintes (3 → 2…), mantém os vencimentos e deixa o total em 4.
  Parcela paga e única parcela não podem ser excluídas.
- [x] Editar o valor sem marcar "aplicar às geradas" não altera cards existentes. Marcado, altera só os
  não concluídos, não arquivados e não excluídos.
- [x] Quantidade abaixo da maior parcela gerada é recusada. Começar na parcela e primeiro vencimento
  ficam travados depois da 1ª geração.
- [x] Renovar 12 → 24 faz todos os selos mostrarem "de 24", e a parcela 13 vence no mês seguinte à 12.
- [x] Excluir custo com parcela gerada é bloqueado. Sem parcela, vai para a lixeira.
- [x] Inativo não gera parcelas. Reativar gera as que entraram na janela.
- [x] No quadro de custos fixos, o card não mostra Evento nem "Enviar para o Financeiro", e exige
  Empresa, inclusive no card avulso.
- [x] Transferir card entre quadro de custos fixos e quadro de evento é recusado nos dois sentidos, e o
  destino nem aparece na lista.
- [x] Filtro "Tipo" separa parcelas de avulsos. O filtro "Empresa" funciona no quadro.
- [x] O selo carrega sem N+1 no `kanbanData`.

## 9. Testes

`tests/Feature/FixedCosts/` (backend apenas, sem testes de front ou e2e):

- `FixedCostCrudTest`: validações, ordem de dependência, permissões (Admin, Coordenador sem evento,
  Coordenador restrito, Usuário), exclusão bloqueada e soft delete, travas da edição.
- `GenerateFixedCostInstallmentsTest`:
  - os dois modos
  - virada de fim de mês (31 → 28/29/30)
  - começar na parcela N
  - atrasadas
  - idempotência e corrida (índice único)
  - card excluído não recriado
  - inativo não gera
  - renovar
  - propagação opcional da edição
- `GenerateFixedCostsCommandTest`: `--date`, lote, só modo `lead_days`.
- `FixedCostInstallmentTest`: excluir parcela pendente e gerada (renumeração, vencimentos mantidos,
  card na lixeira, selo), bloqueio de paga (concluída e etapa final) e da única, parcela de outro custo
  (404), renovar depois de excluir, diminuir o total preservando a exclusão, "Gerar card novamente".
- `FixedCostBoardTest`:
  - chave do quadro e exclusão mútua com `feeds_finance`
  - visibilidade por perfil
  - bloqueio de transferência nos dois sentidos
  - empresa obrigatória e evento proibido nos cards
  - filtro "Tipo"
  - selo presente no JSON do `kanbanData`
- A migration de dados (§1.1) não tem teste automatizado: DDL no MySQL faz commit implícito e quebra o
  `RefreshDatabase`. Foi conferida no banco local: o quadro "Custos Fixos" ficou com `fixed_costs = 1`.
