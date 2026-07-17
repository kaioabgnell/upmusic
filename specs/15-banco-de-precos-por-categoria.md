# 15 — Banco de Preços por Categoria de Fornecedor

> **Modelo recomendado:** `sonnet` (Sonnet 5) — CRUD/consulta bem-especificados, mesmo padrão da Spec 10.
>
> Substitui a abordagem da [Spec 10 — Banco de Preços dos Serviços](10-banco-de-precos.md). O eixo do
> histórico de preços deixa de ser **Serviço + Empresa (cliente)** e passa a ser a **Categoria de
> Fornecedor** (`fornecedor_categorias`, criada na melhoria anterior — ver [CHECKLIST](CHECKLIST.md)).

## 1. Objetivo

Acompanhar a **evolução de preços por categoria de fornecedor** (ex.: "Som", "Segurança", "Limpeza"),
somando os valores realizados em todos os eventos já feitos. O conceito de "Serviço" é **aposentado**: não
há mais cadastro de serviços — todo o controle de preços é feito na Categoria.

Cada fornecedor pertence a uma categoria (já implementado). Quando um card recebe um fornecedor e um
**Valor realizado**, esse valor entra automaticamente no histórico de preços da categoria daquele fornecedor.

## 2. Contexto atual (o que existe hoje)

Toda a estrutura é ancorada em **Serviço** e **Empresa (cliente)** — ver [Spec 10](10-banco-de-precos.md):

- **`services`** (`app/Models/Service.php`): `name, description, category, unit(string livre), active`. CRUD
  completo em `ServiceController` + `Store/UpdateServiceRequest` + `ServicePolicy` + views
  `resources/views/precos/servicos/{index,create,edit}.blade.php`.
- **`service_prices`** (`app/Models/ServicePrice.php`): `service_id(req), empresa_id, fornecedor_id, card_id,
  price, reference_date, notes, created_by`. Índice `(service_id, empresa_id, reference_date)`.
- **`App\Services\PriceHistoryService`**: `historyForEmpresa()` e `lastPriceByEmpresa()` — ambos **agrupam
  por `empresa_id`**.
- **`PriceHistoryController::index()`** (`/precos/evolucao`): filtros `service_id` + `empresa_id`; passa
  `services`, `empresas`, série e comparação por cliente para a view.
- **`precos/evolucao.blade.php`**: select **Serviço** + select **Cliente(empresa)**, gráfico SVG inline
  (`<polyline>`, laranja `#ff8c1e`), tabela com variação absoluta/%, e painel "Último preço por cliente".
- **`precos/servicos/edit.blade.php`**: editor "Registros de preço" (tabela editável inline via JS
  `priceEntries`, colunas Data/Preço/**Cliente**/Fornecedor/Observação) + botões "Registrar preço" e
  "Ver evolução".
- **Sidebar**: item único "Banco de Preços" → `services.index`.
- **Não existe** hook de card → registro de preço; **não existe** enum de unidade (é string livre);
  **não existe** `ServicePricePolicy` (reusa `ServicePolicy`).

## 3. Mudança conceitual

| Eixo | Antes | Depois |
|------|-------|--------|
| Entidade rastreada | Serviço (`services`) | Categoria de fornecedor (`fornecedor_categorias`) |
| Série agrupada por | Empresa (cliente) | Categoria (a série é a própria categoria) |
| Segundo eixo de comparação | cliente (empresa) | fornecedor (quem cobrou) |
| Fonte dos preços | registro manual | registro manual **+ automático** ao salvar card com fornecedor + valor realizado |
| Unidade | string livre no Serviço | enum fixo na Categoria (diária / unidade / hora / serviço completo) |
| Cadastro de Serviço | existe | **removido** |

## 4. Escopo

**Dentro:**
- Enum fixo de **Unidade** (hard-coded, sem tabela) e campo `unidade` no cadastro de Categoria.
- Nova tabela/modelo de registro de preço ancorado na categoria (substitui `service_prices`).
- "Banco de Preços" (`/precos/...`) passa a **listar categorias**; abrir uma categoria mostra os "Registros
  de preço" e um botão "Ver evolução".
- Evolução de preços por **categoria** (remove o select de Serviço e o de Cliente), listando todos os
  preços históricos da categoria em todos os eventos.
- Hook automático: salvar card (criar/editar) com fornecedor + valor realizado grava/atualiza um registro
  de preço na categoria do fornecedor.
- Aposentar o módulo de Serviços (model, controller, requests, policy, rotas, views).

**Fora (não muda agora):**
- Cadastro de Categorias em si (CRUD em `/fornecedor-categorias`) — já existe; só ganha o campo `unidade`.
- Vínculo Fornecedor→Categoria — já existe.
- Planejamento financeiro (Spec 09) — independente.

## 5. Modelo de dados

### 5.1 Enum de unidade (hard-coded)

Novo `app/Domain/Enums/UnidadeMedida.php` (PHP enum `string`), **sem tabela** — os valores vivem no código:

```php
enum UnidadeMedida: string
{
    case Diaria = 'diaria';
    case Unidade = 'unidade';
    case Hora = 'hora';
    case ServicoCompleto = 'servico_completo';

    public function label(): string { /* Diária, Unidade, Hora, Serviço completo */ }

    public static function options(): array { /* value => label, para os selects */ }
}
```

### 5.2 `fornecedor_categorias.unidade`

Migration nova adicionando `->string('unidade', 30)->nullable()->after('nome')`. Model
`FornecedorCategoria`: incluir `unidade` no `$fillable` e cast `'unidade' => UnidadeMedida::class`.

### 5.3 `price_records` (novo — substitui `service_prices`)

```
id
fornecedor_categoria_id  FK fornecedor_categorias  (obrigatório, cascadeOnDelete)
fornecedor_id            FK fornecedores  nullable  nullOnDelete
card_id                  FK cards         nullable  nullOnDelete   (único — ver hook §9)
event_id                 FK events        nullable  nullOnDelete
price                    decimal(15,2)
reference_date           date
notes                    string(255) nullable
created_by               FK users nullable nullOnDelete
timestamps
index (fornecedor_categoria_id, reference_date)
unique (card_id)          // um card gera no máximo 1 registro (upsert idempotente)
```

Model `App\Models\PriceRecord`: relações `categoria()`, `fornecedor()`, `card()`, `event()`, `creator()`;
casts `price:decimal:2`, `reference_date:date`.

> **Decisão de migração (confirmar):** como "Serviço" some e não há mapeamento confiável Serviço→Categoria,
> os dados atuais de `service_prices` **não têm para onde migrar**. Sendo pré-lançamento (base de dev),
> recomendo **criar `price_records` limpa** e **dropar `services` + `service_prices`** (a migration de drop
> com `down()` recriando as tabelas por segurança). Se houver dados reais a preservar, tratar caso a caso
> antes de rodar.

## 6. Unidade no cadastro de Categoria

- `resources/views/fornecedor-categorias/_form.blade.php`: adicionar select **Unidade** (`name="unidade"`)
  populado por `UnidadeMedida::options()`, abaixo do Nome.
- `Store/UpdateFornecedorCategoriaRequest`: validar `'unidade' => ['nullable', Rule::enum(UnidadeMedida::class)]`.
- `fornecedor-categorias/index.blade.php`: nova coluna "Unidade" exibindo `->unidade?->label()`.

## 7. Banco de Preços — lista de categorias (`/precos/categorias`)

Substitui a lista de serviços. Novo `PriceCategoriaController` (ou reaproveitar um controller de preços):

- **index** (`prices.categorias.index`): lista categorias **ativas** com contagem de registros
  (`withCount('priceRecords')`), unidade e ações por linha: **"Registros"** (abre §8) e **"Ver evolução"**
  (`prices.history?categoria_id=`). Botão no topo: "Evolução de preços". Leitura liberada a qualquer
  autenticado (igual ao `services.index` de hoje).
- Sidebar "Banco de Preços" repassa a apontar para `prices.categorias.index`.

## 8. Detalhe da categoria — Registros de preço (`/precos/categorias/{fornecedorCategoria}`)

Reaproveita **exatamente** o editor "Registros de preço" da antiga `precos/servicos/edit.blade.php`
(tabela editável inline via o mesmo JS `priceEntries`), com as diferenças:

- **Sem** o formulário de dados do serviço (nome/unidade são editados no cadastro da categoria, §6). A
  unidade da categoria aparece só como texto/label no cabeçalho.
- Colunas do registro: **Data, Preço, Fornecedor, Evento (opcional), Observação** — a coluna **Cliente
  (empresa) é removida**.
- Formulário de adição: Data, Preço, Fornecedor (opcional), Evento (opcional), Observação.
- Botão **"Ver evolução"** → `prices.history?categoria_id={id}`.
- Registros originados de card aparecem aqui como qualquer outro (com Fornecedor/Evento preenchidos e
  Origem = card); podem ser editados/removidos manualmente pelo coordenador.

## 9. Evolução de preços (`/precos/evolucao`) — pivot

`PriceHistoryController::index()` e `precos/evolucao.blade.php` passam a operar por categoria:

- Filtro único: select **Categoria** (`categoria_id`) — **remove** o select de Serviço e o de Cliente.
- Ao escolher a categoria, listar **todos os preços históricos da categoria, em todos os eventos**, do mais
  recente ao mais antigo, com variação absoluta e % (mesma lógica de variação de hoje, só reancorada).
- Gráfico SVG inline mantido (série cronológica da categoria).
- Tabela de histórico: colunas **Data, Preço, Variação, Fornecedor, Evento, Origem(card)**.
- Painel lateral "Último preço por cliente" → vira **"Último preço por fornecedor"** (comparação natural:
  quanto cada fornecedor cobrou por último naquela categoria). É o análogo direto do que existia; se preferir
  simplificar, pode ser omitido — mas recomendo manter, é barato e útil.

`App\Services\PriceHistoryService` passa a ter `historyForCategoria(FornecedorCategoria $c)` e
`lastPriceByFornecedor(FornecedorCategoria $c)` (substituindo `historyForEmpresa`/`lastPriceByEmpresa`),
consultando `price_records` com eager-load de `fornecedor`, `event`, `card`.

## 10. Hook: card → registro de preço

Regra: ao **criar ou editar** um card, se houver **fornecedor** E **valor realizado** (`actual_value`) > 0,
grava/atualiza um `price_records` para a **categoria do fornecedor**.

- Ação nova `App\Actions\Prices\SyncCardPriceRecord`, chamada por `CreateCard` e `UpdateCard` (centralizado
  no domínio, então vale para qualquer origem — card-panel, importação de template, formulário externo).
- **Idempotente por `card_id`** (upsert): se já existe registro daquele card, atualiza
  `price`/`fornecedor_id`/`fornecedor_categoria_id`/`event_id`; senão cria. Evita duplicar histórico a cada
  salvamento.
- Campos ao criar: `fornecedor_categoria_id` = `fornecedor->fornecedor_categoria_id` (se o fornecedor não
  tiver categoria, **não** grava), `fornecedor_id`, `card_id`, `event_id` = `card->event_id`, `price` =
  `card->actual_value`, `reference_date` = **hoje** na criação (mantida estável em edições posteriores),
  `created_by` = ator.
- **Limpeza**: se o card passar a ficar sem fornecedor ou com valor realizado vazio/zero, o registro
  originado daquele card é **removido** (mantém o histórico coerente com o estado atual do card).
- Não altera registros manuais (sem `card_id`).

> Observação: hoje `card_panel` já tem os campos Fornecedor e Valor realizado; nada muda na UI do card —
> o comportamento é 100% server-side nas Actions.

## 11. Rotas

```
GET    /precos/categorias                                   prices.categorias.index   (lista categorias)
GET    /precos/categorias/{fornecedorCategoria}             prices.categorias.show    (registros de preço)
GET    /precos/evolucao                                     prices.history            (?categoria_id=)
POST   /precos/categorias/{fornecedorCategoria}/registros   prices.store
PUT    /precos/registros/{priceRecord}                      prices.update
DELETE /precos/registros/{priceRecord}                      prices.destroy
```

- index/show/history: dentro de `['auth','active']` (leitura liberada, como hoje).
- store/update/destroy: dentro de `role:admin,coordenador`.
- **Removidas:** `services.index/create/store/edit/update/destroy`, `prices.store/update/destroy` antigas
  (apontavam para `ServicePriceController`). Opcional: manter `/precos/servicos` como redirect 301 para
  `/precos/categorias`.

## 12. Mudanças no backend (arquivos)

- **Novo** `app/Domain/Enums/UnidadeMedida.php`.
- **Novo** `app/Models/PriceRecord.php`.
- **Novas migrations:** `..._add_unidade_to_fornecedor_categorias`, `..._create_price_records_table`,
  `..._drop_services_and_service_prices_tables`.
- **`app/Models/FornecedorCategoria.php`:** `unidade` no fillable + cast; relação `priceRecords(): HasMany`.
- **`app/Http/Controllers/`:** novo `PriceCategoriaController` (index/show/registros) + `PriceRecordController`
  (store/update/destroy, adaptado do atual `ServicePriceController`); `PriceHistoryController` reancorado em
  categoria. **Remover** `ServiceController`, `ServicePriceController`.
- **`app/Services/PriceHistoryService.php`:** `historyForCategoria()` + `lastPriceByFornecedor()`.
- **`app/Actions/Cards/CreateCard.php` e `UpdateCard.php`:** chamar `SyncCardPriceRecord`; **novo**
  `app/Actions/Prices/SyncCardPriceRecord.php`.
- **Form Requests:** `Store/UpdateFornecedorCategoriaRequest` ganham `unidade`; **remover**
  `Store/UpdateServiceRequest`.
- **Policies:** nova `PriceRecordPolicy` (ou reusar `FornecedorCategoriaPolicy` para gestão de registros);
  **remover** `ServicePolicy`. Registrar/desregistrar em `AuthServiceProvider`.
- **`app/Models/Service.php` e `app/Models/ServicePrice.php`:** removidos.

## 13. Mudanças no frontend (arquivos)

- **`resources/views/fornecedor-categorias/_form.blade.php`:** select Unidade.
- **`resources/views/fornecedor-categorias/index.blade.php`:** coluna Unidade.
- **Novas views** `resources/views/precos/categorias/index.blade.php` e `.../show.blade.php` (esta última
  reaproveitando o editor `priceEntries`, sem Cliente, com Evento).
- **`resources/views/precos/evolucao.blade.php`:** select Categoria (remove Serviço + Cliente); tabela com
  Fornecedor/Evento; painel "Último preço por fornecedor".
- **`resources/views/components/sidebar.blade.php`:** "Banco de Preços" → `prices.categorias.index`.
- **Remover** `resources/views/precos/servicos/` (index/create/edit).

## 14. Plano de implementação (incremental)

1. **Enum + unidade na categoria** — `UnidadeMedida`, migration `unidade`, form/requests/index da categoria.
   Validar cadastro por HTTP.
2. **`price_records` + model + PriceHistoryService** reancorado (sem tocar UI ainda). Validar por tinker.
3. **Banco de Preços por categoria** — `PriceCategoriaController` + views index/show (editor de registros) +
   `PriceRecordController` (store/update/destroy) + rotas + sidebar. Validar CRUD de registro por HTTP.
4. **Evolução por categoria** — `PriceHistoryController` + `evolucao.blade.php`. Validar gráfico/série.
5. **Hook card → registro** — `SyncCardPriceRecord` em `CreateCard`/`UpdateCard`. Validar criar/editar/limpar.
6. **Aposentar Serviços** — remover model/controller/requests/policy/rotas/views + migration de drop.
   `pint` + `npm run build`.

## 15. Critérios de aceite

- [x] Cadastro de categoria com **Unidade** (diária/unidade/hora/serviço completo), exibida na listagem.
- [x] "Banco de Preços" lista **categorias** (com unidade e nº de registros), não serviços.
- [x] Abrir uma categoria mostra "Registros de preço" (Data/Preço/Fornecedor/Evento/Observação — sem
      Cliente), com adicionar/editar/excluir.
- [x] "Ver evolução" mostra a evolução **por categoria**: sem select de Serviço, sem Cliente; lista todos os
      preços históricos da categoria em todos os eventos, com variação e gráfico.
- [x] Criar/editar card com fornecedor + valor realizado grava/atualiza **um** registro de preço na categoria
      do fornecedor (idempotente por card); limpar fornecedor/valor remove o registro daquele card.
- [x] Módulo de Serviços removido (rotas, telas, model) sem quebrar o restante do sistema.
- [x] `./vendor/bin/pint` limpo e `npm run build` sem erros.

## 16. Riscos e decisões em aberto

- **Perda de dados de `service_prices`** (§5.3): confirmar que a base é descartável antes de dropar.
- **`reference_date` de registro vindo de card**: definido como "hoje" na criação e estável em edições —
  confirmar se o desejado não seria a data do evento (`event.start_date`). Fácil de trocar.
- **Painel "Último preço por fornecedor"** (§9): incluído como análogo do painel de cliente; pode ser
  cortado se o cliente quiser a tela mais enxuta.
- **Categoria sem unidade**: `unidade` é nullable (categorias já existentes ficam sem unidade até serem
  editadas) — não bloqueia o histórico.
- **Fornecedor sem categoria**: o hook simplesmente não grava (documentado em §10) — sem erro.
