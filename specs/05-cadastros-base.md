# 05 — Cadastros Base

> **Modelo recomendado:** `sonnet` (Sonnet 5).

Cadastros que alimentam os relacionamentos do sistema. Ordem de dependência e schema em
[03-modelo-de-dados.md](03-modelo-de-dados.md).

## 1. Setores (Departamentos)

Cadastro dos setores da empresa (ex.: Orçamentos, Contratos/Jurídico, Financeiro, Conclusão).
Alimenta a criação de quadros (cada quadro referencia um setor — ver [06](06-quadros-e-departamentos.md)).

- **Campos:** nome (único, obrigatório), descrição, cor (hex), ícone (Font Awesome), ativo.
- **Listagem:** nome, descrição, cor/ícone, nº de quadros, status; busca por nome.
- **Regras:** não permitir excluir setor com quadros vinculados (bloquear ou reatribuir); soft delete.
- **Acesso:** Admin e Coordenador.

## 2. Empresas (Clientes)

Empresas vinculáveis aos cards, ao financeiro e ao banco de preços.

- **Campos:** razão social (obrigatório), nome fantasia, CNPJ (único, validado), e-mail, telefone,
  endereço (CEP, logradouro, número, complemento, bairro, cidade, UF), observações, ativo.
- **CNPJ:** máscara na UI, validação de dígitos verificadores; armazenar padronizado. Busca de CEP
  (ViaCEP) opcional para preencher endereço.
- **Listagem:** razão social, nome fantasia, CNPJ, cidade/UF, status; busca por nome/CNPJ; filtro por status.
- **Uso:** select com busca ao vincular ao card; atalho "cadastrar empresa" inline (modal) no card, respeitando
  a dependência (a empresa precisa existir antes do vínculo).
- **Regras:** soft delete; ao excluir, cards mantêm histórico e o vínculo vira nulo (`set null`).
- **Acesso:** Admin e Coordenador (Usuário: leitura/seleção).

## 3. Fornecedores

Fornecedores contratados no processo, classificados por tipo.

- **Campos:** tipo (`PF`/`PJ`, obrigatório), nome/razão social, documento (CPF se PF, CNPJ se PJ —
  único, validado conforme o tipo), e-mail, telefone, categoria (ex.: limpeza, segurança, som),
  observações, ativo.
- **UI:** ao escolher o tipo, ajustar rótulo e máscara do documento (CPF/CNPJ) e validação correspondente.
- **Listagem:** nome, tipo, documento, categoria, status; busca por nome/documento; filtro por tipo/status.
- **Uso:** vinculável em `service_prices` (banco de preços) e referenciável em cards/financeiro.
- **Acesso:** Admin e Coordenador.

## 4. Padrões comuns dos CRUDs

- Index com header (título + botão "Novo …" laranja), busca, filtros, tabela paginada, ações por linha (ícones FA).
- Formulário create/edit em página ou painel lateral; validação via Form Request; feedback SweetAlert2.
- Exclusão sempre com confirmação SweetAlert2; soft delete.
- Estado vazio (`x-empty-state`) com CTA de cadastro.

## 5. Rotas (web)

```
Resource /setores        setores.*        role:admin,coordenador
Resource /empresas       empresas.*       role:admin,coordenador (index/show liberado a usuario)
Resource /fornecedores   fornecedores.*   role:admin,coordenador
POST     /empresas/quick empresas.quick   (cadastro inline via modal a partir do card)
GET      /empresas/buscar (JSON p/ selects com busca)
```

## 6. Critérios de aceite

- [ ] CRUD completo de Setores, Empresas e Fornecedores com validação e soft delete.
- [ ] CNPJ/CPF validados (dígitos) e com máscara; documento único.
- [ ] Fornecedor alterna PF/PJ ajustando rótulo, máscara e validação do documento.
- [ ] Busca e filtros server-side em todas as listagens.
- [ ] Setor com quadros não pode ser excluído sem tratamento.
- [ ] Cadastro inline de empresa disponível no fluxo do card.
