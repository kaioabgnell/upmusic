# 25 — Centro de custos

Cadastro global de **centros de custo** e uso dele para classificar as linhas de **CUSTOS** e
**RECEITAS** do Financeiro do Evento (specs/23), inclusive no modal "Enviar para o Financeiro" do card.

Regras definidas em sessão de perguntas e respostas, em 17/11/2026.

## 1. Cadastro

| Campo | Regra |
|---|---|
| Código | Opcional, até 30 caracteres, único entre os não excluídos (ex.: `MKT-01`, `1.02`) |
| Nome | Obrigatório, até 120 caracteres, único entre os não excluídos |
| Descrição | Opcional, até 1000 caracteres |
| Ativo | Padrão: sim |

- **Escopo global**: um cadastro único, usado por qualquer evento. Não existe centro padrão por evento.
- **Tabela** `cost_centers` (model `CostCenter`), com soft delete. Não há índice único no banco: a
  unicidade é validada nos Form Requests ignorando a lixeira, para permitir recriar um código excluído.
- **Rótulo nas listas**: `código nome` (ex.: `MKT-01 Marketing`), ou só o nome quando não há código.
  A ordenação é por código e depois por nome, com os sem código no fim.
- **Acesso**: Admin e Coordenador sem escopo de evento (`CostCenterPolicy`, mesma regra de Setores).
  Fica no menu **Cadastros → Centros de custo**, logo após Empresas, em `/centros-de-custo`.
- **Listagem**: colunas Código, Centro de custo (nome e descrição), Em uso (custos + receitas), Status e
  Ações. Busca por código ou nome e filtro por status. Paginação de 15 itens.

## 2. Excluir e inativar

- **Em uso** (pelo menos uma linha de custo ou receita não excluída): a exclusão é bloqueada com a
  mensagem "Este centro está em N linha(s) do financeiro. Inative em vez de excluir." Na listagem, o
  botão de excluir aparece desabilitado e explica o motivo no tooltip.
- **Sem uso**: vai para a lixeira (soft delete).
- **Inativo**: some das escolhas novas, mas continua aparecendo e sendo aceito nas linhas que já o
  usam, com o rótulo `… (inativo)`. Os relacionamentos usam `withTrashed()`, então o histórico nunca
  perde o nome.

## 3. Nas linhas do financeiro

- Campo `cost_center_id`, **opcional**, em `finance_cost_items` e `finance_revenues`.
- A validação (`App\Rules\SelectableCostCenter`) aceita vazio, um centro ativo ou o mesmo centro que a
  linha já tem. A última opção existe porque o autosave reenvia a linha inteira.
- **Custos**: coluna "Centro de custo" logo após a Descrição. Item, descrição e centro respondem juntos
  "o que é e onde aloca". Editável inline, com autosave igual às demais colunas.
- **Receitas**: coluna "Centro de custo" logo após a Descrição, na mesma posição de Custos.
- **Filtro em Custos**: seleção múltipla com busca, igual ao filtro Empresa (`cost_center_id[]`).
  A opção "Sem centro de custo" (`none`) fica no topo e encontra as linhas ainda não classificadas.
  Os totais do rodapé respeitam o filtro.
- **Opções dos selects**: centros ativos mais os já usados nas linhas exibidas (`CostCenter::options()`).

## 4. Modal "Enviar para o Financeiro" (card)

- O select "Centro de custo" ocupa a metade livre da linha de "Data de vencimento".
- O centro **mora só na linha do Financeiro**: o card não o guarda e não o espelha.
- Mesma semântica do vencimento em `SyncCardToFinance::sanitize`:
  - só o modal envia a chave `cost_center_id`;
  - chave presente e vazia limpa o centro da linha;
  - os gatilhos automáticos (observer, quadro com `feeds_finance`) não enviam a chave e nunca alteram o centro.
- Numa resincronia, o modal abre com o centro que a linha já tem, mesmo que esteja inativo.

## 5. Exportação Excel

- A coluna **CENTRO DE CUSTO** vai para o **fim** das abas CUSTOS (depois do bloco CONTROLE) e
  RECEITAS (coluna H). O import (`ImportFinanceSpreadsheet`) lê as colunas por posição fixa: inserir a
  coluna depois da Descrição quebraria a reimportação.

## 6. Fora do escopo (por enquanto)

- Ação em massa "Alterar centro de custo" em Custos.
- Filtro em Receitas.
- Quebra por centro de custo no Resumo do evento ou em relatórios.
- Leitura do centro de custo na importação da planilha.
- Centro padrão por evento.

## 7. Testes

`tests/Feature/Finance/CostCenterTest.php`: CRUD, unicidade com a lixeira, exclusão bloqueada e soft
delete, permissões, regra do inativo, filtro com "sem centro", receitas, ponte do card (grava, não é
sobrescrito pela resincronia automática, limpa) e exportação.
