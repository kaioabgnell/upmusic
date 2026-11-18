# 26 — Contas bancárias

Cadastro global de **contas bancárias** das empresas e o vínculo delas com o dinheiro que se move no
Financeiro do Evento (specs/23): de qual conta **sai** cada pagamento de custo e em qual conta **entra**
cada receita.

Definido em 17/11/2026. Decisões tomadas com o usuário: escopo só no Financeiro do Evento (Locações
fica fora por enquanto) e conta **opcional** nos lançamentos. O restante segue o padrão do Centro de
custos (specs/25).

## 1. Cadastro

| Campo | Regra |
|---|---|
| Empresas | Obrigatório: pelo menos uma. Checkbox com busca; uma conta pode ser de várias empresas |
| Banco | Obrigatório, até 100 caracteres. Texto livre com sugestões dos bancos mais comuns (`código - nome`) |
| Nome da conta | Obrigatório, até 120 caracteres. É o nome que aparece nos lançamentos |
| Agência | Obrigatória, até 20 caracteres |
| Conta | Obrigatória, até 30 caracteres. Banco + agência + conta são únicos entre as não excluídas |
| Tipo de conta | Conta corrente, Poupança ou Conta investimento (`BankAccountType`) |
| Situação | Ativa (padrão) ou inativa |

- **Empresas** são as de `/empresas`. A lista do formulário mostra as ativas e as já vinculadas (mesmo
  inativas), para a edição nunca desmarcar nada em silêncio. Sem empresa ativa, o formulário aponta para
  o cadastro de empresas (ordem de dependência).
- **Tabelas**: `bank_accounts` (model `BankAccount`, soft delete) e o pivot `bank_account_empresa`.
  A unicidade é validada no Form Request ignorando a lixeira, sem índice único no banco.
- **Rótulo nos lançamentos**: `Nome (Ag. 1234 · 56789-0)`, em ordem de nome.
- **Acesso**: Admin e Coordenador sem escopo de evento (`BankAccountPolicy`). Menu **Cadastros →
  Contas bancárias**, logo após Centros de custo, em `/contas-bancarias`.
- **Listagem**: colunas Conta (nome e tipo), Banco, Agência / Conta, Empresas (até duas pelo nome e
  "+N", lista completa no tooltip), Em uso, Status e Ações. Busca por nome, banco, agência ou conta;
  filtros por empresa, tipo e situação. Paginação de 15.

## 2. Excluir e inativar

- **Em uso** (pelo menos um pagamento ou receita não excluída): exclusão bloqueada com "Esta conta está
  em N lançamento(s) do financeiro. Inative em vez de excluir." Na listagem, o botão aparece desabilitado.
- **Sem uso**: vai para a lixeira (soft delete).
- **Inativa**: some das escolhas novas, mas continua aparecendo e aceita onde já é usada, com o rótulo
  `… (inativa)`. As relações usam `withTrashed()`.

## 3. Nos lançamentos do financeiro

- Coluna `bank_account_id`, **opcional**, em `finance_payments` e `finance_revenues`.
- Validação (`App\Rules\SelectableBankAccount`): vazio, conta ativa ou a mesma conta que o lançamento
  já tem.
- **Pagamentos de custo** (aba Custos, clique no valor da coluna Pago):
  - O formulário "Lançar pagamento" tem o select "Conta bancária (opcional)" logo abaixo do Grupo de
    pagamento — só contas ativas.
  - Cada pagamento da lista mostra a conta e permite trocá-la ali mesmo (PUT do pagamento). Serve para
    classificar os pagamentos antigos e os da importação, que nascem sem conta. Com a prestação de
    contas fechada, a conta só é exibida.
- **Receitas**: coluna "Conta bancária" editável ao lado de "Recebido por", com autosave.

## 4. Exportação Excel

- Aba **RECEITAS**: coluna **CONTA BANCÁRIA** no fim (coluna I, depois de CENTRO DE CUSTO), porque o
  import lê as colunas por posição.
- Aba **CUSTOS**: sem coluna. Os pagamentos ali são somados por grupo de pagamento; uma linha pode ter
  vários pagamentos de contas diferentes.

## 5. Fora do escopo (por enquanto)

- Pagamentos e recebimentos de **Locações** (specs/24).
- Conta bancária no modal "Enviar para o Financeiro" do card (o card não lança pagamento).
- Filtro por conta nas grades, extrato ou saldo por conta.
- Leitura da conta na importação da planilha.

## 6. Testes

`tests/Feature/Finance/BankAccountTest.php`: CRUD com várias empresas, obrigatórios, unicidade com a
lixeira, exclusão bloqueada e soft delete, permissões, conta no pagamento (registrar, opcional, trocar e
limpar, regra da inativa), opções da tela de Custos, receitas e exportação.
